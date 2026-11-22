<?php

declare(strict_types=1);

namespace App\Referrals\Application;

use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class CommissionQuery
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function list(Membership $actor): array
    {
        $sql = 'SELECT id, lease_id, beneficiary_account_id, beneficiary_label, currency, base_minor, amount_minor, status, earned_at
                FROM commission WHERE organization_id = :org';
        $params = ['org' => $actor->organizationId];
        if ($actor->hasRole('referrer') && !$actor->hasRole('manager')) {
            $sql .= ' AND beneficiary_account_id = :account';
            $params['account'] = $actor->accountId;
        } elseif (!$actor->hasRole('manager')) {
            throw new ApiException(403, 'FORBIDDEN', 'Action interdite pour ce rôle.');
        }
        $sql .= ' ORDER BY earned_at DESC LIMIT 50';
        $items = [];
        foreach ($this->database->fetchAll($sql, $params) as $row) {
            $items[] = $this->present($row);
        }

        return ['items' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(Membership $actor, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, lease_id, beneficiary_account_id, beneficiary_label, currency, base_minor, amount_minor, status, earned_at
             FROM commission WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if ($actor->hasRole('referrer') && !$actor->hasRole('manager') && $row['beneficiary_account_id'] !== $actor->accountId) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if (!$actor->hasRole('manager') && !$actor->hasRole('referrer')) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return $this->present($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return [
            'id' => $row['id'],
            'lease_id' => $row['lease_id'],
            'beneficiary_label' => $row['beneficiary_label'],
            'currency' => $row['currency'],
            'base_minor' => Input::outMinor($row['base_minor']),
            'amount_minor' => Input::outMinor($row['amount_minor']),
            'status' => $row['status'],
            'earned_at' => $row['earned_at'],
        ];
    }
}
