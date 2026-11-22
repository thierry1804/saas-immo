<?php

declare(strict_types=1);

namespace App\Evidence\Application;

use App\Communications\Application\NotificationWriter;
use App\Evidence\Infrastructure\SealSigner;
use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class ReportService
{
    public function __construct(
        private readonly Database $database,
        private readonly Journal $journal,
        private readonly NotificationWriter $notifications,
        private readonly SealSigner $signer,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function review(Membership $actor, string $missionId, array $body): array
    {
        $decision = Input::text($body, 'decision', 40);
        if (!in_array($decision, ['complete', 'unable', 'complement_required'], true)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Décision inconnue.');
        }
        $reason = Input::text($body, 'reason', 2000);
        $visitId = Input::uuid($body, 'visit_id');
        $expected = Input::int($body, 'expected_version');
        $mission = $this->database->fetch(
            'SELECT id, status, version, selected_visit_id, property_id FROM mission WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $missionId],
        );
        if ($mission === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $visit = $this->database->fetch(
            'SELECT id FROM visit_attempt WHERE organization_id = :org AND id = :id AND mission_id = :mission',
            ['org' => $actor->organizationId, 'id' => $visitId, 'mission' => $missionId],
        );
        if ($visit === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if ($mission['selected_visit_id'] !== null && $mission['selected_visit_id'] !== $visitId && $decision === 'complete') {
            throw new ApiException(409, 'VISIT_ALREADY_SELECTED', 'Une autre visite est déjà retenue.');
        }
        if ((int) $mission['version'] !== $expected) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée. Version actuelle : '.$mission['version'].'.');
        }
        $sealed = $this->database->fetch(
            'SELECT id FROM evidence_seal WHERE organization_id = :org AND visit_id = :visit',
            ['org' => $actor->organizationId, 'visit' => $visitId],
        );
        if ($sealed === null && $decision === 'complete') {
            throw new ApiException(409, 'INCOMPLETE_EVIDENCE', 'La visite n’est pas scellée.');
        }
        $status = match ($decision) {
            'complete' => 'completed',
            'unable' => 'unable',
            default => 'complement_required',
        };
        $decisionId = Uuid::v4();
        $this->database->execute(
            'INSERT INTO review_decision (id, organization_id, mission_id, visit_id, decision, reasons, author_id)
             VALUES (:id, :org, :mission, :visit, :decision, :reason, :author)',
            [
                'id' => $decisionId,
                'org' => $actor->organizationId,
                'mission' => $missionId,
                'visit' => $visitId,
                'decision' => $decision,
                'reason' => $reason,
                'author' => $actor->accountId,
            ],
        );
        $selected = $decision === 'complete' ? $visitId : $mission['selected_visit_id'];
        $updated = $this->database->execute(
            'UPDATE mission
             SET status = :status, selected_visit_id = :selected, version = version + 1, updated_at = now()
             WHERE organization_id = :org AND id = :id AND version = :version',
            [
                'status' => $status,
                'selected' => $selected,
                'org' => $actor->organizationId,
                'id' => $missionId,
                'version' => $expected,
            ],
        );
        if ($updated !== 1) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Version périmée.');
        }
        $report = null;
        if ($decision === 'complete') {
            $report = $this->ensureDraft($actor, $missionId, $visitId, (string) $mission['property_id']);
        }
        $this->journal->audit($actor->organizationId, $actor->accountId, 'mission.reviewed', 'review_decision', $decisionId, [
            'decision' => $decision,
            'reason' => $reason,
            'visit_id' => $visitId,
        ]);

        return [
            'mission_id' => $missionId,
            'status' => $status,
            'decision_id' => $decisionId,
            'version' => $expected + 1,
            'report_id' => $report['report_id'] ?? null,
            'report_version_id' => $report['report_version_id'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function publish(Membership $actor, string $reportId, array $body): array
    {
        $versionId = Input::uuid($body, 'report_version_id');
        Input::int($body, 'expected_version');
        $report = $this->database->fetch(
            'SELECT id, mission_id FROM report WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $reportId],
        );
        if ($report === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $version = $this->database->fetch(
            'SELECT id, status, content_snapshot, version_number FROM report_version
             WHERE organization_id = :org AND id = :id AND report_id = :report',
            ['org' => $actor->organizationId, 'id' => $versionId, 'report' => $reportId],
        );
        if ($version === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $existing = $this->database->fetch(
            'SELECT opaque_code, pdf_hash, signed_at FROM publication_receipt
             WHERE organization_id = :org AND report_version_id = :id',
            ['org' => $actor->organizationId, 'id' => $versionId],
        );
        if ($existing !== null) {
            return [
                'report_id' => $reportId,
                'report_version_id' => $versionId,
                'status' => 'published',
                'opaque_code' => $existing['opaque_code'],
                'published_at' => $existing['signed_at'],
            ];
        }
        $reviewed = $this->database->fetch(
            'SELECT id FROM review_decision
             WHERE organization_id = :org AND mission_id = :mission AND decision = \'complete\'',
            ['org' => $actor->organizationId, 'mission' => $report['mission_id']],
        );
        if ($reviewed === null) {
            throw new ApiException(409, 'INCOMPLETE_EVIDENCE', 'La mission n’a pas de décision complète.');
        }
        $this->database->execute(
            'UPDATE report_version SET status = \'published\', published_at = now()
             WHERE organization_id = :org AND id = :id AND status <> \'published\'',
            ['org' => $actor->organizationId, 'id' => $versionId],
        );
        $content = $version['content_snapshot'];
        $encoded = is_string($content) ? $content : json_encode($content, JSON_THROW_ON_ERROR);
        $pdfHash = hash('sha256', $encoded);
        $opaque = bin2hex(random_bytes(16));
        $signed = $this->signer->sign($pdfHash);
        $this->database->execute(
            'INSERT INTO publication_receipt (
                id, organization_id, report_version_id, pdf_hash, manifest_hashes, signed_at, signature, opaque_code
             ) VALUES (
                :id, :org, :version, :hash, :manifests, now(), :signature, :code
             )',
            [
                'id' => Uuid::v4(),
                'org' => $actor->organizationId,
                'version' => $versionId,
                'hash' => $pdfHash,
                'manifests' => '{'.$pdfHash.'}',
                'signature' => $signed['signature'],
                'code' => $opaque,
            ],
        );
        $ownerAccount = $this->database->fetchOne(
            'SELECT o.account_id FROM mission m
             JOIN property p ON p.organization_id = m.organization_id AND p.id = m.property_id
             JOIN owner o ON o.organization_id = p.organization_id AND o.id = p.current_owner_id
             WHERE m.organization_id = :org AND m.id = :id',
            ['org' => $actor->organizationId, 'id' => $report['mission_id']],
        );
        $this->notifications->notify(
            is_string($ownerAccount) ? $ownerAccount : null,
            $actor->organizationId,
            'report_published',
            ['report_id' => $reportId],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'report.published', 'report', $reportId, [
            'report_version_id' => $versionId,
        ]);

        return [
            'report_id' => $reportId,
            'report_version_id' => $versionId,
            'status' => 'published',
            'opaque_code' => $opaque,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function export(Membership $actor, string $reportId, array $body): array
    {
        $report = $this->database->fetch(
            'SELECT id FROM report WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $reportId],
        );
        if ($report === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $type = Input::optionalText($body, 'type', 40) ?? 'pdf';
        $id = Uuid::v4();
        $this->database->execute(
            'INSERT INTO async_operation (id, organization_id, type, requester_id, status, result)
             VALUES (:id, :org, :type, :requester, \'accepted\', CAST(:result AS jsonb))',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'type' => 'report_'.$type,
                'requester' => $actor->accountId,
                'result' => json_encode(['report_id' => $reportId, 'simulated' => true], JSON_THROW_ON_ERROR),
            ],
        );

        return ['operation_id' => $id, 'status_url' => '/api/v1/operations/'.$id];
    }

    /**
     * @return array<string, mixed>
     */
    public function operation(Membership $actor, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, type, status FROM async_operation WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return ['operation_id' => $row['id'], 'type' => $row['type'], 'status' => $row['status']];
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $opaqueCode, ?string $hash): array
    {
        $row = $this->database->fetch(
            'SELECT pdf_hash, signed_at FROM publication_receipt WHERE opaque_code = :code',
            ['code' => $opaqueCode],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $match = null;
        if ($hash !== null && $hash !== '') {
            $match = hash_equals((string) $row['pdf_hash'], strtolower($hash));
        }

        return [
            'status' => 'published',
            'published_at' => $row['signed_at'],
            'file_match' => $match,
        ];
    }

    /**
     * @return array{report_id: string, report_version_id: string}
     */
    private function ensureDraft(Membership $actor, string $missionId, string $visitId, string $propertyId): array
    {
        $report = $this->database->fetch(
            'SELECT id FROM report WHERE organization_id = :org AND mission_id = :mission',
            ['org' => $actor->organizationId, 'mission' => $missionId],
        );
        if ($report === null) {
            $reportId = Uuid::v4();
            $this->database->execute(
                'INSERT INTO report (id, organization_id, mission_id) VALUES (:id, :org, :mission)',
                ['id' => $reportId, 'org' => $actor->organizationId, 'mission' => $missionId],
            );
        } else {
            $reportId = (string) $report['id'];
        }
        $version = $this->database->fetch(
            'SELECT id FROM report_version WHERE organization_id = :org AND report_id = :report AND status = \'draft\'
             ORDER BY version_number DESC LIMIT 1',
            ['org' => $actor->organizationId, 'report' => $reportId],
        );
        if ($version !== null) {
            return ['report_id' => $reportId, 'report_version_id' => (string) $version['id']];
        }
        $sealIds = $this->database->fetchAll(
            'SELECT id FROM evidence_seal WHERE organization_id = :org AND visit_id = :visit',
            ['org' => $actor->organizationId, 'visit' => $visitId],
        );
        $ids = array_map(static fn (array $row): string => (string) $row['id'], $sealIds);
        $snapshot = [
            'mission_id' => $missionId,
            'visit_id' => $visitId,
            'property_ref' => $propertyId,
            'seal_ids' => $ids,
        ];
        $versionId = Uuid::v4();
        $number = (int) $this->database->fetchOne(
            'SELECT COALESCE(MAX(version_number), 0) + 1 FROM report_version WHERE organization_id = :org AND report_id = :report',
            ['org' => $actor->organizationId, 'report' => $reportId],
        );
        $sealLiteral = '{'.implode(',', $ids).'}';
        $this->database->execute(
            'INSERT INTO report_version (
                id, organization_id, report_id, version_number, seal_ids, content_snapshot, status
             ) VALUES (
                :id, :org, :report, :number, CAST(:seals AS uuid[]), CAST(:content AS jsonb), \'draft\'
             )',
            [
                'id' => $versionId,
                'org' => $actor->organizationId,
                'report' => $reportId,
                'number' => $number,
                'seals' => $sealLiteral,
                'content' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            ],
        );
        unset($snapshot);

        return ['report_id' => $reportId, 'report_version_id' => $versionId];
    }
}
