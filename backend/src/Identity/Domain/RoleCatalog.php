<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\SharedKernel\Http\ApiException;

final class RoleCatalog
{
    /** @var list<string> */
    public const ROLES = ['manager', 'agent', 'owner', 'renter', 'referrer'];

    /** @var list<string> */
    public const PERMISSIONS = [
        'operations',
        'prepare_payout',
        'finance_prepare',
        'approve_payout',
        'finance_approve',
        'fieldwork',
        'owner_read',
        'renter_read',
        'referrer_read',
    ];

    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    /**
     * @param list<string> $actorPermissions
     */
    public static function assertGrantable(array $roles, array $permissions, array $actorPermissions): void
    {
        foreach ($roles as $role) {
            if (!in_array($role, self::ROLES, true)) {
                throw new ApiException(422, 'UNPROCESSABLE', 'Rôle inconnu.', false, ['roles' => ['Rôle non autorisé.']]);
            }
        }
        foreach ($permissions as $permission) {
            if (!in_array($permission, self::PERMISSIONS, true)) {
                throw new ApiException(422, 'UNPROCESSABLE', 'Permission inconnue.');
            }
            if (!in_array($permission, $actorPermissions, true)) {
                throw new ApiException(403, 'FORBIDDEN', 'Impossible d’accorder une permission que vous n’avez pas.');
            }
        }
    }
}
