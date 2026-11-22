<?php

declare(strict_types=1);

namespace App\SharedKernel\Security;

use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestContext
{
    public function __construct(
        private readonly RequestStack $requests,
        private readonly AccessTokenCodec $tokens,
        private readonly Database $database,
    ) {
    }

    public function requireActorId(): string
    {
        $request = $this->requests->getCurrentRequest();
        if ($request === null) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Session absente ou expirée.');
        }
        $cached = $request->attributes->get('_veille_actor');
        if (is_array($cached) && is_string($cached['sub'] ?? null)) {
            return $cached['sub'];
        }
        $token = $this->bearer($request);
        if ($token === null) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Session absente ou expirée.');
        }
        try {
            $claims = $this->tokens->parse($token, 'access');
        } catch (ApiException $e) {
            throw $e;
        }
        $request->attributes->set('_veille_actor', $claims);

        return (string) $claims['sub'];
    }

    public function authTime(): int
    {
        $request = $this->requests->getCurrentRequest();
        $cached = $request?->attributes->get('_veille_actor');
        if (!is_array($cached)) {
            $this->requireActorId();
            $cached = $request?->attributes->get('_veille_actor');
        }
        $authTime = is_array($cached) ? ($cached['auth_time'] ?? time()) : time();

        return is_int($authTime) ? $authTime : (int) $authTime;
    }

    /**
     * @param list<string> $roles
     */
    public function requireMembership(array $roles = []): Membership
    {
        $accountId = $this->requireActorId();
        $request = $this->requests->getCurrentRequest();
        $org = $request?->headers->get('X-Organization-Id');
        if (!Uuid::is($org)) {
            throw new ApiException(400, 'BAD_REQUEST', 'En-tête X-Organization-Id (UUID) requis.');
        }
        $row = $this->database->fetch(
            'SELECT m.organization_id, m.account_id, m.roles, m.permissions, m.status, o.status AS org_status
             FROM membership m
             JOIN organization o ON o.id = m.organization_id
             WHERE m.organization_id = :org AND m.account_id = :account',
            ['org' => strtolower((string) $org), 'account' => $accountId],
        );
        if ($row === null || $row['status'] !== 'active') {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $membership = Membership::fromRow($row, $this->authTime());
        if ($roles !== [] && !$membership->hasAnyRole($roles)) {
            throw new ApiException(403, 'FORBIDDEN', 'Action interdite pour ce rôle.');
        }

        return $membership;
    }

    public function assertWritable(Membership $membership): void
    {
        if (in_array($membership->organizationStatus, ['suspended', 'closed'], true)) {
            throw new ApiException(409, 'ORGANIZATION_SUSPENDED', 'Cette organisation est suspendue.');
        }
    }

    public function organizationHeader(): string
    {
        $org = $this->requests->getCurrentRequest()?->headers->get('X-Organization-Id');
        if (!Uuid::is($org)) {
            throw new ApiException(400, 'BAD_REQUEST', 'En-tête X-Organization-Id (UUID) requis.');
        }

        return strtolower((string) $org);
    }

    private function bearer(\Symfony\Component\HttpFoundation\Request $request): ?string
    {
        $header = $request->headers->get('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));

            return $token === '' ? null : $token;
        }
        $cookie = $request->cookies->get('veille_access');
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }

        return null;
    }
}
