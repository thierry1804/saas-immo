<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\RoleCatalog;
use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class DirectoryService
{
    public function __construct(
        private readonly Database $database,
        private readonly Journal $journal,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function registerDevice(Membership $actor, array $body): array
    {
        if (!$actor->hasAnyRole(['agent', 'referrer', 'manager'])) {
            throw new ApiException(403, 'FORBIDDEN', 'Action interdite pour ce rôle.');
        }
        $identifier = Input::text($body, 'device_identifier', 200);
        $pin = Input::text($body, 'pin', 6);
        if (preg_match('/^\d{6}$/', $pin) !== 1) {
            throw new ApiException(422, 'UNPROCESSABLE', 'PIN à six chiffres requis.', false, ['pin' => ['Six chiffres.']]);
        }
        $existing = $this->database->fetch(
            'SELECT id, revoked_at FROM device WHERE organization_id = :org AND device_identifier = :identifier',
            ['org' => $actor->organizationId, 'identifier' => $identifier],
        );
        if ($existing !== null && $existing['revoked_at'] === null) {
            return $this->present($existing['id'], $identifier, false);
        }
        $id = $existing['id'] ?? Uuid::v4();
        $publicKey = Input::optionalText($body, 'public_key', 4000);
        if ($existing === null) {
            $this->database->execute(
                'INSERT INTO device (id, organization_id, account_id, device_identifier, public_key, pin_hash)
                 VALUES (:id, :org, :account, :identifier, :public, :pin)',
                [
                    'id' => $id,
                    'org' => $actor->organizationId,
                    'account' => $actor->accountId,
                    'identifier' => $identifier,
                    'public' => $publicKey,
                    'pin' => password_hash($pin, PASSWORD_DEFAULT),
                ],
            );
        } else {
            $this->database->execute(
                'UPDATE device SET revoked_at = NULL, account_id = :account, pin_hash = :pin, public_key = COALESCE(:public, public_key), updated_at = now()
                 WHERE id = :id AND organization_id = :org',
                [
                    'id' => $id,
                    'org' => $actor->organizationId,
                    'account' => $actor->accountId,
                    'pin' => password_hash($pin, PASSWORD_DEFAULT),
                    'public' => $publicKey,
                ],
            );
        }
        $this->journal->audit($actor->organizationId, $actor->accountId, 'device.registered', 'device', (string) $id, [
            'device_identifier' => $identifier,
        ]);

        return $this->present((string) $id, $identifier, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function setPin(Membership $actor, string $deviceId, string $pin): array
    {
        if (preg_match('/^\d{6}$/', $pin) !== 1) {
            throw new ApiException(422, 'UNPROCESSABLE', 'PIN à six chiffres requis.');
        }
        $row = $this->requireDevice($actor->organizationId, $deviceId);
        if ($row['account_id'] !== $actor->accountId && !$actor->hasRole('manager')) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $this->database->execute(
            'UPDATE device SET pin_hash = :pin, updated_at = now() WHERE id = :id AND organization_id = :org',
            ['pin' => password_hash($pin, PASSWORD_DEFAULT), 'id' => $deviceId, 'org' => $actor->organizationId],
        );

        return ['id' => $deviceId, 'pin_set' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function revoke(Membership $actor, string $deviceId, string $reason): array
    {
        $row = $this->requireDevice($actor->organizationId, $deviceId);
        if ($row['account_id'] !== $actor->accountId && !$actor->hasRole('manager')) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $this->database->execute(
            'UPDATE device SET revoked_at = COALESCE(revoked_at, now()), updated_at = now() WHERE id = :id AND organization_id = :org',
            ['id' => $deviceId, 'org' => $actor->organizationId],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'device.revoked', 'device', $deviceId, [
            'reason' => $reason,
        ]);

        return ['id' => $deviceId, 'revoked' => true];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function invite(Membership $actor, string $organizationId, array $body): array
    {
        if ($organizationId !== $actor->organizationId) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $accountRef = strtolower(Input::text($body, 'account_ref', 200));
        $roles = $this->stringList($body['roles'] ?? null, 'roles');
        $permissions = $this->stringList($body['permissions'] ?? [], 'permissions');
        RoleCatalog::assertGrantable($roles, $permissions, $actor->permissions);
        $account = $this->database->fetch(
            'SELECT id FROM account WHERE email_normalized = :v OR phone_normalized = :v',
            ['v' => $accountRef],
        );
        if ($account === null) {
            $accountId = Uuid::v4();
            $email = str_contains($accountRef, '@') ? $accountRef : null;
            $phone = $email === null ? $accountRef : null;
            $this->database->execute(
                'INSERT INTO account (id, display_name, email_normalized, phone_normalized, status)
                 VALUES (:id, :name, :email, :phone, \'active\')',
                [
                    'id' => $accountId,
                    'name' => 'Invité',
                    'email' => $email,
                    'phone' => $phone,
                ],
            );
        } else {
            $accountId = (string) $account['id'];
        }
        $membershipId = Uuid::v4();
        $this->database->execute(
            'INSERT INTO membership (id, organization_id, account_id, roles, permissions, status)
             VALUES (:id, :org, :account, CAST(:roles AS text[]), CAST(:perms AS text[]), \'invited\')
             ON CONFLICT (organization_id, account_id) DO UPDATE
             SET roles = EXCLUDED.roles, permissions = EXCLUDED.permissions, status = \'invited\', updated_at = now()',
            [
                'id' => $membershipId,
                'org' => $actor->organizationId,
                'account' => $accountId,
                'roles' => Membership::toPgArray($roles),
                'perms' => Membership::toPgArray($permissions),
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'membership.invited', 'membership', $accountId, [
            'roles' => $roles,
        ]);

        return ['account_id' => $accountId, 'status' => 'invited', 'roles' => $roles];
    }

    /**
     * @return array<string, mixed>
     */
    private function requireDevice(string $organizationId, string $deviceId): array
    {
        $row = $this->database->fetch(
            'SELECT id, account_id, revoked_at FROM device WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $deviceId],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(mixed $id, string $identifier, bool $created): array
    {
        return [
            'id' => (string) $id,
            'device_identifier' => $identifier,
            'created' => $created,
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $field): array
    {
        if ($field === 'permissions' && $value === []) {
            return [];
        }
        if (!is_array($value) || $value === []) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Liste requise.', false, [$field => ['Liste requise.']]);
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new ApiException(422, 'UNPROCESSABLE', 'Liste invalide.', false, [$field => ['Textes requis.']]);
            }
            $out[] = $item;
        }

        return $out;
    }
}
