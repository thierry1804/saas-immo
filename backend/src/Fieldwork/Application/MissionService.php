<?php

declare(strict_types=1);

namespace App\Fieldwork\Application;

use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class MissionService
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
        $agentId = Input::uuid($body, 'agent_id');
        $checklistId = Input::uuid($body, 'checklist_version_id');
        $type = Input::text($body, 'type', 40);
        if (!in_array($type, ['visit', 'inventory_entry', 'inventory_exit', 'control', 'prospect'], true)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Type de mission inconnu.');
        }
        $this->requireProperty($actor->organizationId, $propertyId);
        $this->requireAgent($actor->organizationId, $agentId);
        $checklist = $this->database->fetch(
            'SELECT id, status FROM checklist_version WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $checklistId],
        );
        if ($checklist === null || $checklist['status'] !== 'published') {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $start = Input::dateTime($body, 'scheduled_start');
        $end = isset($body['scheduled_end']) ? Input::dateTime($body, 'scheduled_end') : null;
        $id = Uuid::v4();
        $this->database->execute(
            'INSERT INTO mission (
                id, organization_id, property_id, type, scheduled_start, scheduled_end, status,
                assignment_revision, checklist_version_id, instructions_snapshot
             ) VALUES (
                :id, :org, :property, :type, :start, :end, \'assigned\', 1, :checklist, CAST(:instructions AS jsonb)
             )',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'property' => $propertyId,
                'type' => $type,
                'start' => $start->format('Y-m-d H:i:sP'),
                'end' => $end?->format('Y-m-d H:i:sP'),
                'checklist' => $checklistId,
                'instructions' => json_encode(['note' => Input::optionalText($body, 'note', 1000)], JSON_THROW_ON_ERROR),
            ],
        );
        $this->database->execute(
            'INSERT INTO assignment_history (id, organization_id, mission_id, agent_id, revision, valid_from, reason)
             VALUES (:id, :org, :mission, :agent, 1, :start, :reason)',
            [
                'id' => Uuid::v4(),
                'org' => $actor->organizationId,
                'mission' => $id,
                'agent' => $agentId,
                'start' => $start->format('Y-m-d H:i:sP'),
                'reason' => 'Création',
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'mission.created', 'mission', $id, [
            'agent_id' => $agentId,
        ]);

        return $this->get($actor->organizationId, $id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function reassign(Membership $actor, string $id, array $body): array
    {
        $expected = Input::int($body, 'expected_version');
        $agentId = Input::uuid($body, 'agent_id');
        $reason = Input::text($body, 'reason', 500);
        $this->requireAgent($actor->organizationId, $agentId);
        $mission = $this->find($actor->organizationId, $id);
        if ((int) $mission['version'] !== $expected) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée. Version actuelle : '.$mission['version'].'.');
        }
        $revision = (int) $mission['assignment_revision'] + 1;
        $this->database->execute(
            'INSERT INTO assignment_history (id, organization_id, mission_id, agent_id, revision, valid_from, reason)
             VALUES (:id, :org, :mission, :agent, :revision, now(), :reason)',
            [
                'id' => Uuid::v4(),
                'org' => $actor->organizationId,
                'mission' => $id,
                'agent' => $agentId,
                'revision' => $revision,
                'reason' => $reason,
            ],
        );
        $updated = $this->database->execute(
            'UPDATE mission
             SET assignment_revision = :revision, version = version + 1, updated_at = now()
             WHERE organization_id = :org AND id = :id AND version = :version',
            [
                'revision' => $revision,
                'org' => $actor->organizationId,
                'id' => $id,
                'version' => $expected,
            ],
        );
        if ($updated !== 1) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée.');
        }
        $this->journal->audit($actor->organizationId, $actor->accountId, 'mission.reassigned', 'mission', $id, [
            'agent_id' => $agentId,
            'reason' => $reason,
            'revision' => $revision,
        ]);

        return $this->get($actor->organizationId, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function transition(Membership $actor, string $id, string $status, array $body): array
    {
        $expected = Input::int($body, 'expected_version');
        $reason = Input::text($body, 'reason', 500);
        $mission = $this->find($actor->organizationId, $id);
        if (in_array($mission['status'], ['cancelled', 'interrupted'], true) && $mission['status'] === $status) {
            return $this->present($mission);
        }
        if ((int) $mission['version'] !== $expected) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée. Version actuelle : '.$mission['version'].'.');
        }
        $updated = $this->database->execute(
            'UPDATE mission SET status = :status, version = version + 1, updated_at = now()
             WHERE organization_id = :org AND id = :id AND version = :version',
            ['status' => $status, 'org' => $actor->organizationId, 'id' => $id, 'version' => $expected],
        );
        if ($updated !== 1) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée.');
        }
        $this->journal->audit($actor->organizationId, $actor->accountId, 'mission.'.$status, 'mission', $id, [
            'reason' => $reason,
        ]);

        return $this->get($actor->organizationId, $id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function createRecurrence(Membership $actor, array $body): array
    {
        $propertyId = Input::uuid($body, 'property_id');
        $agentId = Input::uuid($body, 'agent_id');
        $checklistId = Input::uuid($body, 'checklist_version_id');
        $this->requireProperty($actor->organizationId, $propertyId);
        $this->requireAgent($actor->organizationId, $agentId);
        $checklist = $this->database->fetch(
            'SELECT id FROM checklist_version WHERE organization_id = :org AND id = :id AND status = \'published\'',
            ['org' => $actor->organizationId, 'id' => $checklistId],
        );
        if ($checklist === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $id = Uuid::v4();
        $this->database->execute(
            'INSERT INTO recurrence (id, organization_id, property_id, rule, timezone, agent_id, checklist_version_id)
             VALUES (:id, :org, :property, :rule, :timezone, :agent, :checklist)',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'property' => $propertyId,
                'rule' => Input::text($body, 'rule', 200),
                'timezone' => Input::text($body, 'timezone', 80),
                'agent' => $agentId,
                'checklist' => $checklistId,
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'recurrence.created', 'recurrence', $id, []);

        return ['id' => $id, 'version' => 1, 'active' => true];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function getFor(Membership $actor, string $id): array
    {
        $mission = $this->find($actor->organizationId, $id);
        if ($actor->hasRole('agent') && !$actor->hasRole('manager')) {
            $assigned = $this->database->fetch(
                'SELECT id FROM assignment_history
                 WHERE organization_id = :org AND mission_id = :mission AND agent_id = :agent AND revision = :revision',
                [
                    'org' => $actor->organizationId,
                    'mission' => $id,
                    'agent' => $actor->accountId,
                    'revision' => $mission['assignment_revision'],
                ],
            );
            if ($assigned === null) {
                throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
            }
        }

        return $this->present($mission);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $organizationId, string $id): array
    {
        return $this->present($this->find($organizationId, $id));
    }

    /**
     * @param array<string, mixed> $mission
     * @return array<string, mixed>
     */
    private function present(array $mission): array
    {
        return [
            'id' => $mission['id'],
            'property_id' => $mission['property_id'],
            'type' => $mission['type'],
            'status' => $mission['status'],
            'scheduled_start' => $mission['scheduled_start'],
            'scheduled_end' => $mission['scheduled_end'],
            'assignment_revision' => (int) $mission['assignment_revision'],
            'checklist_version_id' => $mission['checklist_version_id'],
            'selected_visit_id' => $mission['selected_visit_id'],
            'version' => (int) $mission['version'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function find(string $organizationId, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, property_id, type, status, scheduled_start, scheduled_end, assignment_revision,
                    checklist_version_id, selected_visit_id, version
             FROM mission WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return $row;
    }

    private function requireProperty(string $organizationId, string $id): void
    {
        $row = $this->database->fetch(
            'SELECT id FROM property WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
    }

    private function requireAgent(string $organizationId, string $accountId): void
    {
        $row = $this->database->fetch(
            'SELECT id FROM membership
             WHERE organization_id = :org AND account_id = :account AND status = \'active\' AND \'agent\' = ANY(roles)',
            ['org' => $organizationId, 'account' => $accountId],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
    }
}
