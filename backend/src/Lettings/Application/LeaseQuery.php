<?php

declare(strict_types=1);

namespace App\Lettings\Application;

use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class LeaseQuery
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(Membership $actor, string $id): array
    {
        $row = $this->visible($actor, $id);

        return [
            'id' => $row['id'],
            'property_id' => $row['property_id'],
            'label' => $row['label'],
            'status' => $row['status'],
            'currency' => $row['currency'],
            'rent_minor' => Input::outMinor($row['rent_minor']),
            'charges_minor' => Input::outMinor($row['charges_minor']),
            'deposit_minor' => Input::outMinor($row['deposit_minor']),
            'management_fee_bps' => (int) $row['management_fee_bps'],
            'start_date' => $row['start_date'],
            'due_day' => $row['due_day'] === null ? null : (int) $row['due_day'],
            'version' => (int) $row['version'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schedule(Membership $actor, string $id): array
    {
        $lease = $this->visible($actor, $id);
        $rows = $this->database->fetchAll(
            'SELECT id, period_start, period_end, rent_minor, charges_minor, currency, status
             FROM rent_schedule
             WHERE organization_id = :org AND lease_id = :lease
             ORDER BY period_start',
            ['org' => $actor->organizationId, 'lease' => $id],
        );
        $items = [];
        foreach ($rows as $row) {
            if ($row['currency'] !== $lease['currency']) {
                throw new ApiException(422, 'CURRENCY_MISMATCH', 'Échéance dans une autre monnaie que le bail.');
            }
            $items[] = [
                'id' => $row['id'],
                'period_start' => $row['period_start'],
                'period_end' => $row['period_end'],
                'rent_minor' => Input::outMinor($row['rent_minor']),
                'charges_minor' => Input::outMinor($row['charges_minor']),
                'currency' => $row['currency'],
                'status' => $row['status'],
            ];
        }

        return [
            'lease_id' => $id,
            'currency' => $lease['currency'],
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function visible(Membership $actor, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, property_id, label, status, currency, rent_minor, charges_minor, deposit_minor,
                    management_fee_bps, start_date, due_day, renter_account_id, version
             FROM lease WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if ($actor->hasRole('manager')) {
            return $row;
        }
        if ($actor->hasRole('renter') && $row['renter_account_id'] === $actor->accountId) {
            return $row;
        }
        if ($actor->hasRole('owner')) {
            $owned = $this->database->fetch(
                'SELECT p.id FROM property p
                 JOIN owner o ON o.organization_id = p.organization_id AND o.id = p.current_owner_id
                 WHERE p.organization_id = :org AND p.id = :property AND o.account_id = :account',
                ['org' => $actor->organizationId, 'property' => $row['property_id'], 'account' => $actor->accountId],
            );
            if ($owned !== null) {
                return $row;
            }
        }
        throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
    }
}
