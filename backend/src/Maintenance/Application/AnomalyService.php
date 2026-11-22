<?php

declare(strict_types=1);

namespace App\Maintenance\Application;

use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Money\Money;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class AnomalyService
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
    public function report(Membership $actor, array $body): array
    {
        $propertyId = Input::uuid($body, 'property_id');
        $property = $this->database->fetch(
            'SELECT id FROM property WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $propertyId],
        );
        if ($property === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $id = Uuid::v4();
        $sourceId = isset($body['source_id']) ? Input::uuid($body, 'source_id') : null;
        $this->database->execute(
            'INSERT INTO anomaly (
                id, organization_id, property_id, source_type, source_id, category, severity, status, note, author_id
             ) VALUES (
                :id, :org, :property, :source_type, :source_id, :category, :severity, \'open\', :note, :author
             )',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'property' => $propertyId,
                'source_type' => Input::text($body, 'source_type', 40),
                'source_id' => $sourceId,
                'category' => Input::text($body, 'category', 80),
                'severity' => Input::text($body, 'severity', 40),
                'note' => Input::text($body, 'note', 4000),
                'author' => $actor->accountId,
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'anomaly.reported', 'anomaly', $id, [
            'category' => $body['category'],
            'severity' => $body['severity'],
        ]);

        return $this->get($actor->organizationId, $id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function quote(Membership $actor, string $anomalyId, array $body): array
    {
        $anomaly = $this->database->fetch(
            'SELECT id FROM anomaly WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $anomalyId],
        );
        if ($anomaly === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $money = Money::of(Input::currency($body['currency'] ?? null), Input::minor($body, 'amount_minor'));
        $fees = isset($body['fees_minor'])
            ? Money::of($money->currency, Input::minor($body, 'fees_minor'))
            : Money::of($money->currency, '0');
        $fees->assertSameCurrency($money);
        $number = (int) $this->database->fetchOne(
            'SELECT COALESCE(MAX(version_number), 0) + 1 FROM quote WHERE organization_id = :org AND anomaly_id = :id',
            ['org' => $actor->organizationId, 'id' => $anomalyId],
        );
        $id = Uuid::v4();
        $this->database->execute(
            'INSERT INTO quote (
                id, organization_id, anomaly_id, version_number, supplier_name, description,
                amount_minor, currency, fees_minor, status
             ) VALUES (
                :id, :org, :anomaly, :number, :supplier, :description, :amount, :currency, :fees, \'submitted\'
             )',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'anomaly' => $anomalyId,
                'number' => $number,
                'supplier' => Input::text($body, 'supplier_name', 200),
                'description' => Input::text($body, 'description', 2000),
                'amount' => $money->amountMinor,
                'currency' => $money->currency,
                'fees' => $fees->amountMinor,
            ],
        );

        return [
            'id' => $id,
            'anomaly_id' => $anomalyId,
            'version_number' => $number,
            'amount_minor' => $money->amountMinor,
            'fees_minor' => $fees->amountMinor,
            'currency' => $money->currency,
            'status' => 'submitted',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $organizationId, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, property_id, source_type, source_id, category, severity, status, note, author_id, version, created_at
             FROM anomaly WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return [
            'id' => $row['id'],
            'property_id' => $row['property_id'],
            'source_type' => $row['source_type'],
            'category' => $row['category'],
            'severity' => $row['severity'],
            'status' => $row['status'],
            'note' => $row['note'],
            'author_id' => $row['author_id'],
            'version' => (int) $row['version'],
            'created_at' => $row['created_at'],
        ];
    }
}
