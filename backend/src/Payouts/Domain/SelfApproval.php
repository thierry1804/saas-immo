<?php

declare(strict_types=1);

namespace App\Payouts\Domain;

use App\SharedKernel\Http\ApiException;

final class SelfApproval
{
    public static function forbid(string $preparedBy, string $approver): void
    {
        if ($preparedBy === $approver) {
            throw new ApiException(403, 'SELF_APPROVAL_FORBIDDEN', 'La personne qui prépare un lot ne peut pas l’approuver.');
        }
    }
}
