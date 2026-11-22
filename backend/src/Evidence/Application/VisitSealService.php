<?php

declare(strict_types=1);

namespace App\Evidence\Application;

use App\Evidence\Infrastructure\SealSigner;
use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\JsonCanon;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class VisitSealService
{
    public function __construct(
        private readonly Database $database,
        private readonly MediaService $media,
        private readonly SealSigner $signer,
        private readonly Journal $journal,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function finalize(Membership $actor, string $visitId, array $body): array
    {
        $visit = $this->visit($actor, $visitId);
        $expected = Input::int($body, 'expected_version');
        if ((int) $visit['version'] !== $expected) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée. Version actuelle : '.$visit['version'].'.');
        }
        $manifest = $body['manifest'] ?? null;
        if (!is_array($manifest)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Manifeste requis.');
        }
        $hash = JsonCanon::hash($manifest);
        $given = $body['manifest_hash'] ?? null;
        if (!is_string($given) || preg_match('/^[a-f0-9]{64}$/', $given) !== 1 || !hash_equals($hash, $given)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'manifest_hash ne correspond pas au manifeste canonique.', false, [
                'manifest_hash' => [$hash],
            ]);
        }
        $minPhotos = (int) $this->database->fetchOne(
            'SELECT c.min_photos FROM visit_attempt v
             JOIN mission m ON m.organization_id = v.organization_id AND m.id = v.mission_id
             JOIN checklist_version c ON c.organization_id = m.organization_id AND c.id = m.checklist_version_id
             WHERE v.organization_id = :org AND v.id = :id',
            ['org' => $actor->organizationId, 'id' => $visitId],
        );
        $count = $this->media->verifiedCount($actor->organizationId, $visitId);
        $listed = $manifest['media'] ?? [];
        $missing = !is_array($listed);
        if (is_array($listed)) {
            foreach ($listed as $mediaId) {
                if (!is_string($mediaId) || !$this->mediaVerified($actor->organizationId, $visitId, $mediaId)) {
                    $missing = true;
                }
            }
        }
        if ($missing || $count < $minPhotos) {
            throw new ApiException(422, 'MISSING_MEDIA', 'Médias manquants pour sceller la visite.', false, [
                'media' => ['Minimum '.$minPhotos.' média(s) vérifié(s), '.$count.' présent(s).'],
            ]);
        }
        $existing = $this->database->fetch(
            'SELECT id, manifest_hash, manifest_version, signature, key_id, received_at
             FROM evidence_seal WHERE organization_id = :org AND visit_id = :visit
             ORDER BY received_at DESC LIMIT 1',
            ['org' => $actor->organizationId, 'visit' => $visitId],
        );
        if ($existing !== null) {
            if (hash_equals((string) $existing['manifest_hash'], $hash)) {
                return $this->sealPayload($existing);
            }
            throw new ApiException(409, 'VERSION_CONFLICT', 'Constat déjà scellé. Passez par un amendement.');
        }
        return $this->insertSeal($actor, $visitId, $hash, $manifest, null, 1);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function amend(Membership $actor, string $visitId, array $body): array
    {
        $this->visit($actor, $visitId);
        $reason = Input::text($body, 'reason', 2000);
        $parent = $this->database->fetch(
            'SELECT id, manifest_version FROM evidence_seal
             WHERE organization_id = :org AND visit_id = :visit
             ORDER BY received_at DESC LIMIT 1',
            ['org' => $actor->organizationId, 'visit' => $visitId],
        );
        if ($parent === null) {
            throw new ApiException(409, 'INCOMPLETE_EVIDENCE', 'Aucun scellé à amender.');
        }
        $note = Input::optionalText($body, 'note', 4000) ?? '';
        $manifest = [
            'schema_version' => 1,
            'amendment_of' => $parent['id'],
            'reason' => $reason,
            'note' => $note,
        ];
        $hash = JsonCanon::hash($manifest);
        $seal = $this->insertSeal($actor, $visitId, $hash, $manifest, (string) $parent['id'], (int) $parent['manifest_version'] + 1);
        $this->journal->audit($actor->organizationId, $actor->accountId, 'visit.amended', 'evidence_seal', $seal['id'], [
            'reason' => $reason,
            'parent_seal_id' => $parent['id'],
        ]);

        return $seal;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private function insertSeal(
        Membership $actor,
        string $visitId,
        string $hash,
        array $manifest,
        ?string $parentId,
        int $version,
    ): array {
        $id = Uuid::v4();
        $signed = $this->signer->sign($hash);
        $this->database->execute(
            'INSERT INTO evidence_seal (
                id, organization_id, visit_id, manifest_version, manifest_hash, signature, key_id, parent_seal_id
             ) VALUES (
                :id, :org, :visit, :version, :hash, :signature, :key, :parent
             )',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'visit' => $visitId,
                'version' => $version,
                'hash' => $hash,
                'signature' => $signed['signature'],
                'key' => $signed['key_id'],
                'parent' => $parentId,
            ],
        );
        $this->database->execute(
            'UPDATE visit_attempt SET reception_status = \'complete\', updated_at = now()
             WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $visitId],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'visit.sealed', 'evidence_seal', $id, [
            'manifest_hash' => $hash,
            'manifest_version' => $version,
        ]);
        unset($manifest);

        return [
            'id' => $id,
            'visit_id' => $visitId,
            'manifest_hash' => $hash,
            'manifest_version' => $version,
            'signature' => $signed['signature'],
            'key_id' => $signed['key_id'],
            'parent_seal_id' => $parentId,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sealPayload(array $row): array
    {
        return [
            'id' => $row['id'],
            'manifest_hash' => $row['manifest_hash'],
            'manifest_version' => (int) $row['manifest_version'],
            'signature' => $row['signature'],
            'key_id' => $row['key_id'],
            'received_at' => $row['received_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function visit(Membership $actor, string $visitId): array
    {
        $row = $this->database->fetch(
            'SELECT id, agent_id, version FROM visit_attempt WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $visitId],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if ($actor->hasRole('agent') && !$actor->hasRole('manager') && $row['agent_id'] !== $actor->accountId) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return $row;
    }

    private function mediaVerified(string $organizationId, string $visitId, string $mediaId): bool
    {
        if (!Uuid::is($mediaId)) {
            return false;
        }
        $row = $this->database->fetch(
            'SELECT id FROM media_asset
             WHERE organization_id = :org AND id = :id AND owner_type = \'visit\' AND owner_id = :visit AND status = \'verified\'',
            ['org' => $organizationId, 'id' => $mediaId, 'visit' => $visitId],
        );

        return $row !== null;
    }
}
