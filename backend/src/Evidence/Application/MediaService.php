<?php

declare(strict_types=1);

namespace App\Evidence\Application;

use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class MediaService
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function initUpload(Membership $actor, array $body): array
    {
        $parentType = Input::text($body, 'parent_type', 40);
        $parentId = Input::uuid($body, 'parent_id');
        $this->assertParent($actor->organizationId, $parentType, $parentId);
        $sha = Input::text($body, 'sha256', 64);
        if (preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Empreinte SHA-256 invalide.');
        }
        $size = Input::int($body, 'size_bytes');
        if ($size < 1) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Taille de média invalide.');
        }
        $id = Uuid::v4();
        $this->database->execute(
            'INSERT INTO media_asset (id, organization_id, owner_type, owner_id, storage_key, size_bytes, mime, sha256, status)
             VALUES (:id, :org, :type, :owner, :key, :size, :mime, :sha, \'uploading\')',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'type' => $parentType,
                'owner' => $parentId,
                'key' => 'simulated/'.$actor->organizationId.'/'.$id,
                'size' => $size,
                'mime' => Input::text($body, 'mime', 80),
                'sha' => $sha,
            ],
        );

        return [
            'upload_id' => $id,
            'status' => 'uploading',
            'storage' => 'simulated',
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function complete(Membership $actor, string $id, array $body): array
    {
        $row = $this->database->fetch(
            'SELECT id, sha256, status FROM media_asset WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $sha = Input::text($body, 'sha256', 64);
        if (!hash_equals((string) $row['sha256'], $sha)) {
            $this->database->execute(
                'UPDATE media_asset SET status = \'rejected\' WHERE organization_id = :org AND id = :id AND status = \'uploading\'',
                ['org' => $actor->organizationId, 'id' => $id],
            );
            throw new ApiException(422, 'UNPROCESSABLE', 'L’empreinte finale ne correspond pas.');
        }
        if ($row['status'] === 'verified') {
            return ['id' => $id, 'status' => 'verified'];
        }
        $this->database->execute(
            'UPDATE media_asset SET status = \'verified\', finalized_at = now()
             WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $id],
        );

        return ['id' => $id, 'status' => 'verified'];
    }

    public function attachVerified(
        string $organizationId,
        string $ownerType,
        string $ownerId,
        string $mediaId,
        string $sha,
        int $size,
        string $mime,
    ): void {
        if (preg_match('/^[a-f0-9]{64}$/', $sha) !== 1 || $size < 1) {
            throw new ApiException(422, 'MISSING_MEDIA', 'Média incomplet : empreinte ou taille manquante.');
        }
        $existing = $this->database->fetch(
            'SELECT organization_id, sha256, status FROM media_asset WHERE id = :id',
            ['id' => $mediaId],
        );
        if ($existing !== null && $existing['organization_id'] !== $organizationId) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if ($existing !== null) {
            if (!hash_equals((string) $existing['sha256'], $sha)) {
                throw new ApiException(409, 'IDEMPOTENCY_KEY_REUSED', 'media_id déjà utilisé avec une autre empreinte.');
            }

            return;
        }
        $this->database->execute(
            'INSERT INTO media_asset (id, organization_id, owner_type, owner_id, storage_key, size_bytes, mime, sha256, status, finalized_at)
             VALUES (:id, :org, :type, :owner, :key, :size, :mime, :sha, \'verified\', now())',
            [
                'id' => $mediaId,
                'org' => $organizationId,
                'type' => $ownerType,
                'owner' => $ownerId,
                'key' => 'simulated/'.$organizationId.'/'.$mediaId,
                'size' => $size,
                'mime' => $mime,
                'sha' => $sha,
            ],
        );
    }

    public function verifiedCount(string $organizationId, string $visitId): int
    {
        return (int) $this->database->fetchOne(
            'SELECT COUNT(*) FROM media_asset
             WHERE organization_id = :org AND owner_type = \'visit\' AND owner_id = :owner AND status = \'verified\'',
            ['org' => $organizationId, 'owner' => $visitId],
        );
    }

    private function assertParent(string $organizationId, string $type, string $id): void
    {
        $sql = match ($type) {
            'visit' => 'SELECT id FROM visit_attempt WHERE organization_id = :org AND id = :id',
            'anomaly' => 'SELECT id FROM anomaly WHERE organization_id = :org AND id = :id',
            'property' => 'SELECT id FROM property WHERE organization_id = :org AND id = :id',
            default => null,
        };
        if ($sql === null) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Type de parent inconnu.');
        }
        $row = $this->database->fetch($sql, ['org' => $organizationId, 'id' => $id]);
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
    }
}
