<?php

declare(strict_types=1);

namespace App\Lettings\Application;

use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class ListingService
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
        $propertyId = Input::uuid($body, 'property_id');
        $property = $this->database->fetch(
            'SELECT id, neighborhood FROM property WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $propertyId],
        );
        if ($property === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $content = [
            'title' => Input::text($body, 'title', 160),
            'neighborhood' => Input::optionalText($body, 'neighborhood', 160) ?? $property['neighborhood'],
            'rooms' => isset($body['rooms']) ? Input::int($body, 'rooms') : null,
        ];
        $id = Uuid::v4();
        $slug = bin2hex(random_bytes(16));
        $this->database->execute(
            'INSERT INTO listing (id, organization_id, property_id, content, status, slug)
             VALUES (:id, :org, :property, CAST(:content AS jsonb), \'draft\', :slug)',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'property' => $propertyId,
                'content' => json_encode($content, JSON_THROW_ON_ERROR),
                'slug' => $slug,
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'listing.created', 'listing', $id, []);

        return $this->present($actor->organizationId, $id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function publish(Membership $actor, string $id, array $body): array
    {
        $expected = Input::int($body, 'expected_version');
        $row = $this->find($actor->organizationId, $id);
        if ((int) $row['version'] !== $expected) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée. Version actuelle : '.$row['version'].'.');
        }
        if (($body['owner_agreement'] ?? false) !== true) {
            throw new ApiException(422, 'UNPROCESSABLE', 'L’accord du propriétaire est requis.');
        }
        if ($row['status'] !== 'published') {
            $this->database->execute(
                'UPDATE listing
                 SET status = \'published\', owner_agreement = true, published_at = now(), version = version + 1, updated_at = now()
                 WHERE organization_id = :org AND id = :id AND version = :version',
                ['org' => $actor->organizationId, 'id' => $id, 'version' => $expected],
            );
            $this->journal->audit($actor->organizationId, $actor->accountId, 'listing.published', 'listing', $id, [
                'owner_agreement' => true,
            ]);
        }

        return $this->present($actor->organizationId, $id);
    }

    public function organizationOfPublishedSlug(string $slug): string
    {
        $row = $this->database->fetch(
            'SELECT organization_id FROM listing WHERE slug = :slug AND status = \'published\'',
            ['slug' => $slug],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return (string) $row['organization_id'];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicCard(string $slug): array
    {
        $row = $this->database->fetch(
            'SELECT slug, content, status FROM listing WHERE slug = :slug AND status = \'published\'',
            ['slug' => $slug],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $content = is_string($row['content']) ? json_decode($row['content'], true) : $row['content'];
        if (!is_array($content)) {
            $content = [];
        }

        return [
            'slug' => $row['slug'],
            'title' => $content['title'] ?? null,
            'neighborhood' => $content['neighborhood'] ?? null,
            'rooms' => $content['rooms'] ?? null,
            'status' => 'published',
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function requestVisit(string $slug, array $body): array
    {
        $listing = $this->database->fetch(
            'SELECT id, organization_id FROM listing WHERE slug = :slug AND status = \'published\'',
            ['slug' => $slug],
        );
        if ($listing === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if (($body['policy_accepted'] ?? false) !== true) {
            throw new ApiException(422, 'UNPROCESSABLE', 'La politique de visite doit être acceptée.');
        }
        $start = Input::dateTime($body, 'slot_start');
        $end = Input::dateTime($body, 'slot_end');
        if ($end <= $start) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Le créneau est invalide.');
        }
        $taken = $this->database->fetch(
            'SELECT id FROM viewing_request
             WHERE organization_id = :org AND listing_id = :listing
               AND (status = \'confirmed\' OR (status = \'held\' AND hold_expires_at > now()))
               AND slot_start < :end AND slot_end > :start
             LIMIT 1',
            [
                'org' => $listing['organization_id'],
                'listing' => $listing['id'],
                'start' => $start->format('Y-m-d H:i:sP'),
                'end' => $end->format('Y-m-d H:i:sP'),
            ],
        );
        if ($taken !== null) {
            throw new ApiException(409, 'SLOT_TAKEN', 'Ce créneau n’est plus disponible.');
        }
        $id = Uuid::v4();
        $this->database->execute(
            'INSERT INTO viewing_request (
                id, organization_id, listing_id, contact_label, contact_phone, slot_start, slot_end,
                status, hold_expires_at, policy_version, source
             ) VALUES (
                :id, :org, :listing, :label, :phone, :start, :end, \'held\', now() + interval \'15 minutes\', 1, :source
             )',
            [
                'id' => $id,
                'org' => $listing['organization_id'],
                'listing' => $listing['id'],
                'label' => Input::text($body, 'contact_label', 160),
                'phone' => Input::text($body, 'contact_phone', 40),
                'start' => $start->format('Y-m-d H:i:sP'),
                'end' => $end->format('Y-m-d H:i:sP'),
                'source' => Input::optionalText($body, 'source', 80),
            ],
        );

        return [
            'id' => $id,
            'status' => 'held',
            'slot_start' => $start->format('Y-m-d\TH:i:s\Z'),
            'slot_end' => $end->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(string $organizationId, string $id): array
    {
        $row = $this->find($organizationId, $id);
        $content = is_string($row['content']) ? json_decode($row['content'], true) : $row['content'];

        return [
            'id' => $row['id'],
            'property_id' => $row['property_id'],
            'slug' => $row['slug'],
            'status' => $row['status'],
            'content' => $content,
            'version' => (int) $row['version'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function find(string $organizationId, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, property_id, content, status, slug, version FROM listing WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return $row;
    }
}
