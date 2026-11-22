<?php

declare(strict_types=1);

namespace App\SharedKernel\Audit;

use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;

final class Journal
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function audit(
        string $organizationId,
        ?string $actorId,
        string $action,
        string $objectType,
        ?string $objectId,
        array $payload,
    ): void {
        $this->database->execute(
            'INSERT INTO audit_event (id, organization_id, actor_id, action, object_type, object_id, payload)
             VALUES (:id, :org, :actor, :action, :type, :object, CAST(:payload AS jsonb))',
            [
                'id' => Uuid::v4(),
                'org' => $organizationId,
                'actor' => $actorId,
                'action' => $action,
                'type' => $objectType,
                'object' => $objectId,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function outbox(
        string $organizationId,
        string $aggregateType,
        string $aggregateId,
        int $version,
        string $eventType,
        ?string $actorId,
        array $payload,
    ): void {
        $eventId = Uuid::v4();
        $this->database->execute(
            'INSERT INTO outbox_event (
                id, organization_id, event_id, aggregate_type, aggregate_id, aggregate_version,
                event_type, occurred_at, actor_id, payload
             ) VALUES (
                :id, :org, :event, :type, :aggregate, :version, :event_type, now(), :actor, CAST(:payload AS jsonb)
             )',
            [
                'id' => Uuid::v4(),
                'org' => $organizationId,
                'event' => $eventId,
                'type' => $aggregateType,
                'aggregate' => $aggregateId,
                'version' => $version,
                'event_type' => $eventType,
                'actor' => $actorId,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ],
        );
    }
}
