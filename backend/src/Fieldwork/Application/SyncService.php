<?php

declare(strict_types=1);

namespace App\Fieldwork\Application;

use App\Communications\Application\NotificationWriter;
use App\Evidence\Application\MediaService;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\JsonCanon;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;
use Doctrine\DBAL\Exception\DriverException;

final class SyncService
{
    /** @var list<string> */
    private const TYPES = [
        'urgent_alert_raised',
        'visit_started',
        'position_recorded',
        'checklist_answer_recorded',
        'photo_captured',
        'anomaly_reported',
        'visit_impossible_declared',
        'prospect_presence_recorded',
        'inventory_ack_collected',
        'clock_anchor_observed',
        'visit_closed_local',
    ];

    public function __construct(
        private readonly Database $database,
        private readonly MediaService $media,
        private readonly NotificationWriter $notifications,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(Membership $actor, string $deviceId, ?string $cursor): array
    {
        $this->requireDevice($actor, $deviceId);
        $params = [
            'org' => $actor->organizationId,
            'agent' => $actor->accountId,
        ];
        $cursorSql = '';
        if ($cursor !== null && $cursor !== '') {
            $cursorSql = ' AND m.updated_at > CAST(:cursor AS timestamptz)';
            $params['cursor'] = $cursor;
        }
        $rows = $this->database->fetchAll(
            'SELECT m.id, m.property_id, m.type, m.status, m.scheduled_start, m.scheduled_end,
                    m.assignment_revision, m.checklist_version_id, m.updated_at,
                    p.address, p.neighborhood, p.timezone
             FROM mission m
             JOIN assignment_history a
               ON a.organization_id = m.organization_id
              AND a.mission_id = m.id
              AND a.revision = m.assignment_revision
             JOIN property p ON p.organization_id = m.organization_id AND p.id = m.property_id
             WHERE m.organization_id = :org
               AND a.agent_id = :agent'.$cursorSql.'
             ORDER BY m.scheduled_start
             LIMIT 50',
            $params,
        );
        $missions = [];
        foreach ($rows as $row) {
            $missions[] = [
                'id' => $row['id'],
                'property_id' => $row['property_id'],
                'type' => $row['type'],
                'status' => $row['status'],
                'scheduled_start' => $row['scheduled_start'],
                'scheduled_end' => $row['scheduled_end'],
                'assignment_revision' => (int) $row['assignment_revision'],
                'checklist_version_id' => $row['checklist_version_id'],
                'address' => $row['address'],
                'neighborhood' => $row['neighborhood'],
                'timezone' => $row['timezone'],
                'checklist' => $this->checklist((string) $actor->organizationId, (string) $row['checklist_version_id']),
            ];
        }

        return [
            'missions' => $missions,
            'next_cursor' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function push(Membership $actor, array $body): array
    {
        $deviceId = $body['device_id'] ?? null;
        if (!\App\SharedKernel\Ids\Uuid::is($deviceId)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'device_id requis.');
        }
        $this->requireDevice($actor, (string) $deviceId);
        $actions = $body['actions'] ?? null;
        if (!is_array($actions) || $actions === [] || count($actions) > 100) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Le lot doit contenir entre 1 et 100 actions.');
        }

        return $this->database->tx($actor->organizationId, function () use ($actor, $deviceId, $actions): array {
            $results = [];
            foreach ($actions as $action) {
                if (!is_array($action)) {
                    throw new ApiException(400, 'BAD_REQUEST', 'Action illisible.');
                }
                $results[] = $this->one($actor, (string) $deviceId, $action);
            }

            return ['results' => $results];
        });
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function one(Membership $actor, string $deviceId, array $action): array
    {
        $actionId = $action['action_id'] ?? null;
        if (!Uuid::is($actionId)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'action_id UUID requis.');
        }
        $type = $action['type'] ?? null;
        $payload = $action['payload'] ?? null;
        if (!is_string($type) || !is_array($payload)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Action incomplète.');
        }
        $hash = JsonCanon::hash([
            'type' => $type,
            'payload' => $payload,
            'sequence_number' => $action['sequence_number'] ?? null,
            'local_visit_id' => $action['local_visit_id'] ?? null,
            'mission_id' => $action['mission_id'] ?? null,
            'assignment_revision' => $action['assignment_revision'] ?? null,
        ]);
        $existing = $this->database->fetch(
            'SELECT payload_hash, result_status, visit_id, server_event_id
             FROM sync_action_receipt
             WHERE organization_id = :org AND action_id = :action',
            ['org' => $actor->organizationId, 'action' => $actionId],
        );
        if ($existing !== null) {
            if (!hash_equals((string) $existing['payload_hash'], $hash)) {
                throw new ApiException(409, 'IDEMPOTENCY_KEY_REUSED', 'action_id déjà utilisé avec un autre contenu.');
            }

            return [
                'action_id' => $actionId,
                'status' => 'duplicate',
                'error_code' => null,
                'server_event_id' => $existing['server_event_id'],
                'visit_id' => $existing['visit_id'],
            ];
        }
        if (!in_array($type, self::TYPES, true)) {
            return $this->store($actor, (string) $actionId, $hash, 'rejected', null, 'UNPROCESSABLE');
        }
        try {
            $visitId = $this->apply($actor, $deviceId, (string) $actionId, $type, $action, $payload);
        } catch (ApiException $exception) {
            if ($exception->status === 409) {
                throw $exception;
            }

            return $this->store($actor, (string) $actionId, $hash, 'rejected', null, $exception->errorCode);
        }

        return $this->store($actor, (string) $actionId, $hash, 'accepted', $visitId, null);
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $payload
     */
    private function apply(Membership $actor, string $deviceId, string $actionId, string $type, array $action, array $payload): ?string
    {
        if ($type === 'urgent_alert_raised') {
            $this->notifications->notifyManagers(
                $actor->organizationId,
                'urgent_alert',
                ['severity' => $payload['severity'] ?? 'high', 'description' => $payload['description'] ?? ''],
            );

            return $this->visitId($actor, $action);
        }
        $visitId = $this->ensureVisit($actor, $deviceId, $action, $type === 'visit_started');
        if ($visitId === null) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Visite inconnue pour cette action.');
        }
        if ($type === 'photo_captured') {
            $this->media->attachVerified(
                $actor->organizationId,
                'visit',
                $visitId,
                isset($payload['media_id']) && Uuid::is($payload['media_id']) ? (string) $payload['media_id'] : Uuid::v4(),
                isset($payload['sha256']) && is_string($payload['sha256']) ? $payload['sha256'] : str_repeat('0', 64),
                isset($payload['size_bytes']) && is_int($payload['size_bytes']) ? $payload['size_bytes'] : 0,
                isset($payload['mime']) && is_string($payload['mime']) ? $payload['mime'] : 'image/jpeg',
            );
        }
        if ($type === 'checklist_answer_recorded') {
            $key = $payload['item_key'] ?? null;
            $answer = $payload['answer'] ?? null;
            if (!is_string($key) || !is_string($answer)) {
                throw new ApiException(422, 'UNPROCESSABLE', 'Réponse de checklist incomplète.');
            }
            $version = $this->database->fetchOne(
                'SELECT m.checklist_version_id FROM visit_attempt v
                 JOIN mission m ON m.organization_id = v.organization_id AND m.id = v.mission_id
                 WHERE v.organization_id = :org AND v.id = :id',
                ['org' => $actor->organizationId, 'id' => $visitId],
            );
            $this->database->execute(
                'INSERT INTO checklist_answer (id, organization_id, visit_id, stable_item_key, checklist_version_id, answer, note)
                 VALUES (:id, :org, :visit, :key, :version, :answer, :note)
                 ON CONFLICT (organization_id, visit_id, stable_item_key)
                 DO UPDATE SET answer = EXCLUDED.answer, note = EXCLUDED.note',
                [
                    'id' => Uuid::v4(),
                    'org' => $actor->organizationId,
                    'visit' => $visitId,
                    'key' => $key,
                    'version' => $version,
                    'answer' => $answer,
                    'note' => isset($payload['note']) && is_string($payload['note']) ? $payload['note'] : null,
                ],
            );
        }
        if ($type === 'anomaly_reported') {
            $mission = $this->database->fetch(
                'SELECT property_id FROM visit_attempt v
                 JOIN mission m ON m.organization_id = v.organization_id AND m.id = v.mission_id
                 WHERE v.organization_id = :org AND v.id = :id',
                ['org' => $actor->organizationId, 'id' => $visitId],
            );
            if ($mission !== null) {
                $this->database->execute(
                    'INSERT INTO anomaly (
                        id, organization_id, property_id, source_type, source_id, category, severity, status, note, author_id
                     ) VALUES (
                        :id, :org, :property, \'visit\', :source, :category, :severity, \'open\', :note, :author
                     )',
                    [
                        'id' => Uuid::v4(),
                        'org' => $actor->organizationId,
                        'property' => $mission['property_id'],
                        'source' => $visitId,
                        'category' => is_string($payload['category'] ?? null) ? $payload['category'] : 'autre',
                        'severity' => is_string($payload['severity'] ?? null) ? $payload['severity'] : 'medium',
                        'note' => is_string($payload['note'] ?? null) ? $payload['note'] : '',
                        'author' => $actor->accountId,
                    ],
                );
            }
        }
        $this->insertFact($actor, $visitId, $actionId, $action, $payload);

        return $visitId;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function ensureVisit(Membership $actor, string $deviceId, array $action, bool $create): ?string
    {
        $local = $action['local_visit_id'] ?? null;
        $missionId = $action['mission_id'] ?? null;
        if (!Uuid::is($local) || !Uuid::is($missionId)) {
            if (!$create) {
                return $this->visitId($actor, $action);
            }
            throw new ApiException(422, 'UNPROCESSABLE', 'mission_id et local_visit_id requis.');
        }
        $mission = $this->database->fetch(
            'SELECT id, assignment_revision, status FROM mission WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $missionId],
        );
        if ($mission === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $agent = $this->database->fetchOne(
            'SELECT agent_id FROM assignment_history
             WHERE organization_id = :org AND mission_id = :id AND revision = :revision',
            [
                'org' => $actor->organizationId,
                'id' => $missionId,
                'revision' => $mission['assignment_revision'],
            ],
        );
        if ($agent !== $actor->accountId) {
            throw new ApiException(409, 'ASSIGNMENT_CONFLICT', 'La mission n’est plus affectée à cet agent.');
        }
        $revision = $action['assignment_revision'] ?? null;
        if ((int) $revision !== (int) $mission['assignment_revision']) {
            throw new ApiException(409, 'ASSIGNMENT_CONFLICT', 'La révision d’affectation ne correspond pas.');
        }
        $existing = $this->database->fetch(
            'SELECT id FROM visit_attempt WHERE organization_id = :org AND local_visit_id = :local',
            ['org' => $actor->organizationId, 'local' => $local],
        );
        if ($existing !== null) {
            return (string) $existing['id'];
        }
        if (!$create) {
            return null;
        }
        $id = Uuid::v4();
        $this->database->execute(
            'INSERT INTO visit_attempt (
                id, organization_id, mission_id, agent_id, device_id, assignment_revision, type, local_visit_id, reception_status
             ) VALUES (
                :id, :org, :mission, :agent, :device, :revision, \'visit\', :local, \'receiving\'
             )',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'mission' => $missionId,
                'agent' => $actor->accountId,
                'device' => $deviceId,
                'revision' => $mission['assignment_revision'],
                'local' => $local,
            ],
        );

        return $id;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function visitId(Membership $actor, array $action): ?string
    {
        $local = $action['local_visit_id'] ?? null;
        if (!Uuid::is($local)) {
            return null;
        }
        $row = $this->database->fetch(
            'SELECT id FROM visit_attempt WHERE organization_id = :org AND local_visit_id = :local',
            ['org' => $actor->organizationId, 'local' => $local],
        );

        return $row === null ? null : (string) $row['id'];
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $payload
     */
    private function insertFact(Membership $actor, string $visitId, string $actionId, array $action, array $payload): void
    {
        $sequence = $action['sequence_number'] ?? 1;
        if (!is_int($sequence)) {
            $sequence = is_string($sequence) && ctype_digit($sequence) ? (int) $sequence : 1;
        }
        try {
            $this->database->execute(
                'INSERT INTO visit_fact (
                    id, organization_id, visit_id, action_id, sequence_number, type, payload, device_time, payload_hash
                 ) VALUES (
                    :id, :org, :visit, :action, :sequence, :type, CAST(:payload AS jsonb), :device_time, :hash
                 )',
                [
                    'id' => Uuid::v4(),
                    'org' => $actor->organizationId,
                    'visit' => $visitId,
                    'action' => $actionId,
                    'sequence' => $sequence,
                    'type' => $action['type'],
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'device_time' => is_string($action['captured_at_device'] ?? null) ? $action['captured_at_device'] : null,
                    'hash' => JsonCanon::hash($payload),
                ],
            );
        } catch (DriverException $exception) {
            if (str_contains($exception->getMessage(), 'immutable')) {
                throw new ApiException(409, 'VERSION_CONFLICT', 'Le constat est déjà scellé.');
            }
            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function store(
        Membership $actor,
        string $actionId,
        string $hash,
        string $status,
        ?string $visitId,
        ?string $error,
    ): array {
        $eventId = Uuid::v4();
        $this->database->execute(
            'INSERT INTO sync_action_receipt (
                id, organization_id, action_id, payload_hash, result_status, visit_id, server_event_id
             ) VALUES (
                :id, :org, :action, :hash, :status, :visit, :event
             )',
            [
                'id' => $eventId,
                'org' => $actor->organizationId,
                'action' => $actionId,
                'hash' => $hash,
                'status' => $status,
                'visit' => $visitId,
                'event' => $eventId,
            ],
        );

        return [
            'action_id' => $actionId,
            'status' => $status,
            'error_code' => $error,
            'server_event_id' => $eventId,
            'visit_id' => $visitId,
        ];
    }

    private function requireDevice(Membership $actor, string $deviceId): void
    {
        $row = $this->database->fetch(
            'SELECT account_id, revoked_at FROM device WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $deviceId],
        );
        if ($row === null || $row['account_id'] !== $actor->accountId) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if ($row['revoked_at'] !== null) {
            throw new ApiException(403, 'DEVICE_REVOKED', 'Cet appareil est révoqué.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function checklist(string $organizationId, string $versionId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT stable_item_key, sort_order, label, instruction
             FROM checklist_item
             WHERE organization_id = :org AND version_id = :id
             ORDER BY sort_order',
            ['org' => $organizationId, 'id' => $versionId],
        );
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'stable_item_key' => $row['stable_item_key'],
                'sort_order' => (int) $row['sort_order'],
                'label' => $row['label'],
                'instruction' => $row['instruction'],
            ];
        }

        return $items;
    }
}
