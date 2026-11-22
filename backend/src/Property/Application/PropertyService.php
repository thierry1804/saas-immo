<?php

declare(strict_types=1);

namespace App\Property\Application;

use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class PropertyService
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
    public function create(Membership $actor, array $body): array
    {
        $ownerId = Input::uuid($body, 'owner_id');
        $owner = $this->database->fetch(
            'SELECT id FROM owner WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $ownerId],
        );
        if ($owner === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $id = Uuid::v4();
        $address = Input::text($body, 'address', 500);
        $timezone = Input::text($body, 'timezone', 80);
        $this->database->execute(
            'INSERT INTO property (id, organization_id, current_owner_id, address, neighborhood, lat_e7, lon_e7, timezone, status, characteristics)
             VALUES (:id, :org, :owner, :address, :neighborhood, :lat, :lon, :timezone, \'active\', CAST(:chars AS jsonb))',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'owner' => $ownerId,
                'address' => $address,
                'neighborhood' => Input::optionalText($body, 'neighborhood', 160),
                'lat' => $this->optionalInt($body, 'latitude_e7'),
                'lon' => $this->optionalInt($body, 'longitude_e7'),
                'timezone' => $timezone,
                'chars' => json_encode(is_array($body['characteristics'] ?? null) ? $body['characteristics'] : [], JSON_THROW_ON_ERROR),
            ],
        );
        $this->database->execute(
            'INSERT INTO property_ownership (id, organization_id, property_id, owner_id, valid_from, reason)
             VALUES (:id, :org, :property, :owner, CURRENT_DATE, :reason)',
            [
                'id' => Uuid::v4(),
                'org' => $actor->organizationId,
                'property' => $id,
                'owner' => $ownerId,
                'reason' => 'Création',
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'property.created', 'property', $id, []);

        return $this->get($actor->organizationId, $id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function patch(Membership $actor, string $id, array $body): array
    {
        $expected = Input::int($body, 'expected_version');
        $current = $this->find($actor->organizationId, $id);
        if ((int) $current['version'] !== $expected) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée. Version actuelle : '.$current['version'].'.');
        }
        $address = array_key_exists('address', $body) ? Input::text($body, 'address', 500) : $current['address'];
        $neighborhood = array_key_exists('neighborhood', $body) ? Input::optionalText($body, 'neighborhood', 160) : $current['neighborhood'];
        $status = array_key_exists('status', $body) ? Input::text($body, 'status', 40) : $current['status'];
        $updated = $this->database->execute(
            'UPDATE property
             SET address = :address, neighborhood = :neighborhood, status = :status, version = version + 1, updated_at = now()
             WHERE organization_id = :org AND id = :id AND version = :version',
            [
                'address' => $address,
                'neighborhood' => $neighborhood,
                'status' => $status,
                'org' => $actor->organizationId,
                'id' => $id,
                'version' => $expected,
            ],
        );
        if ($updated !== 1) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée.');
        }
        $this->journal->audit($actor->organizationId, $actor->accountId, 'property.updated', 'property', $id, [
            'previous_address' => $current['address'],
            'previous_version' => $expected,
        ]);

        return $this->get($actor->organizationId, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $organizationId, string $id): array
    {
        $row = $this->find($organizationId, $id);

        return [
            'id' => $row['id'],
            'owner_id' => $row['current_owner_id'],
            'address' => $row['address'],
            'neighborhood' => $row['neighborhood'],
            'timezone' => $row['timezone'],
            'status' => $row['status'],
            'version' => (int) $row['version'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function find(string $organizationId, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, current_owner_id, address, neighborhood, timezone, status, version
             FROM property WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function optionalInt(array $body, string $field): ?int
    {
        if (!array_key_exists($field, $body) || $body[$field] === null) {
            return null;
        }

        return Input::int($body, $field);
    }
}
