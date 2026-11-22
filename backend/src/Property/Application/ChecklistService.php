<?php

declare(strict_types=1);

namespace App\Property\Application;

use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class ChecklistService
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
    public function createVersion(Membership $actor, string $templateId, array $body): array
    {
        $template = $this->database->fetch(
            'SELECT id FROM checklist_template WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $templateId],
        );
        if ($template === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $number = (int) $this->database->fetchOne(
            'SELECT COALESCE(MAX(number), 0) + 1 FROM checklist_version WHERE organization_id = :org AND template_id = :id',
            ['org' => $actor->organizationId, 'id' => $templateId],
        );
        $id = Uuid::v4();
        $minPhotos = array_key_exists('min_photos', $body) ? Input::int($body, 'min_photos') : 6;
        $this->database->execute(
            'INSERT INTO checklist_version (id, organization_id, template_id, number, min_photos, status)
             VALUES (:id, :org, :template, :number, :min, \'draft\')',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'template' => $templateId,
                'number' => $number,
                'min' => $minPhotos,
            ],
        );
        $items = $body['items'] ?? null;
        if (!is_array($items)) {
            $previous = $this->database->fetch(
                'SELECT id FROM checklist_version
                 WHERE organization_id = :org AND template_id = :template AND number = :number',
                ['org' => $actor->organizationId, 'template' => $templateId, 'number' => $number - 1],
            );
            if ($previous !== null) {
                $this->database->execute(
                    'INSERT INTO checklist_item (id, organization_id, version_id, stable_item_key, sort_order, label, instruction, constraints)
                     SELECT gen_random_uuid(), organization_id, :new, stable_item_key, sort_order, label, instruction, constraints
                     FROM checklist_item WHERE organization_id = :org AND version_id = :old',
                    ['new' => $id, 'org' => $actor->organizationId, 'old' => $previous['id']],
                );
            }
        } else {
            $sort = 1;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new ApiException(422, 'UNPROCESSABLE', 'Point de checklist invalide.');
                }
                $this->database->execute(
                    'INSERT INTO checklist_item (id, organization_id, version_id, stable_item_key, sort_order, label, instruction, constraints)
                     VALUES (:id, :org, :version, :key, :sort, :label, :instruction, CAST(:constraints AS jsonb))',
                    [
                        'id' => Uuid::v4(),
                        'org' => $actor->organizationId,
                        'version' => $id,
                        'key' => Input::text($item, 'stable_item_key', 80),
                        'sort' => $sort,
                        'label' => Input::text($item, 'label', 200),
                        'instruction' => Input::optionalText($item, 'instruction', 1000),
                        'constraints' => json_encode(is_array($item['constraints'] ?? null) ? $item['constraints'] : [], JSON_THROW_ON_ERROR),
                    ],
                );
                ++$sort;
            }
        }
        $this->journal->audit($actor->organizationId, $actor->accountId, 'checklist.version_drafted', 'checklist_version', $id, [
            'number' => $number,
        ]);

        return ['id' => $id, 'number' => $number, 'status' => 'draft', 'version' => 1];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function publish(Membership $actor, string $id, array $body): array
    {
        $expected = Input::int($body, 'expected_version');
        $row = $this->database->fetch(
            'SELECT id, status, version, number FROM checklist_version WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if ((int) $row['version'] !== $expected) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée. Version actuelle : '.$row['version'].'.');
        }
        if ($row['status'] === 'published') {
            return ['id' => $id, 'status' => 'published', 'version' => (int) $row['version']];
        }
        $count = (int) $this->database->fetchOne(
            'SELECT COUNT(*) FROM checklist_item WHERE organization_id = :org AND version_id = :id',
            ['org' => $actor->organizationId, 'id' => $id],
        );
        if ($count < 1) {
            throw new ApiException(422, 'UNPROCESSABLE', 'La checklist ne contient aucun point.');
        }
        $this->database->execute(
            'UPDATE checklist_version
             SET status = \'published\', published_at = now(), version = version + 1
             WHERE organization_id = :org AND id = :id AND status = \'draft\' AND version = :version',
            ['org' => $actor->organizationId, 'id' => $id, 'version' => $expected],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'checklist.published', 'checklist_version', $id, []);

        return ['id' => $id, 'status' => 'published', 'version' => $expected + 1];
    }
}
