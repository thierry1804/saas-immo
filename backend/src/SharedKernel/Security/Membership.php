<?php

declare(strict_types=1);

namespace App\SharedKernel\Security;

use App\SharedKernel\Http\ApiException;

final readonly class Membership
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    public function __construct(
        public string $organizationId,
        public string $accountId,
        public array $roles,
        public array $permissions,
        public string $organizationStatus,
        public int $authTime,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row, int $authTime): self
    {
        return new self(
            (string) $row['organization_id'],
            (string) $row['account_id'],
            self::pgArray((string) $row['roles']),
            self::pgArray((string) $row['permissions']),
            (string) $row['org_status'],
            $authTime,
        );
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * @param list<string> $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function requirePermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw new ApiException(403, 'FORBIDDEN', 'Action interdite pour ce compte.');
        }
    }

    public function requireRecentAuth(int $seconds = 300): void
    {
        if ($this->authTime < time() - $seconds) {
            throw new ApiException(403, 'FORBIDDEN', 'Authentification trop ancienne pour cette décision.');
        }
    }

    /**
     * @return list<string>
     */
    public static function pgArray(string $value): array
    {
        $value = trim($value);
        if ($value === '' || $value === '{}') {
            return [];
        }
        $inner = trim($value, '{}');
        if ($inner === '') {
            return [];
        }
        $items = str_getcsv($inner);
        $out = [];
        foreach ($items as $item) {
            $item = trim($item, " \t\n\r\0\x0B\"");
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $items
     */
    public static function toPgArray(array $items): string
    {
        $safe = [];
        foreach ($items as $item) {
            if (preg_match('/^[a-z0-9_]+$/', $item) !== 1) {
                throw new ApiException(422, 'UNPROCESSABLE', 'Rôle ou permission inconnu.');
            }
            $safe[] = $item;
        }

        return '{'.implode(',', $safe).'}';
    }
}
