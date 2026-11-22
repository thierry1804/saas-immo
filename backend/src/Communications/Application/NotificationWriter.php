<?php

declare(strict_types=1);

namespace App\Communications\Application;

use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;

final class NotificationWriter
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function notify(?string $accountId, string $organizationId, string $template, array $payload): void
    {
        $this->database->execute(
            'INSERT INTO notification (id, organization_id, account_id, channel, template, payload, status)
             VALUES (:id, :org, :account, \'in_app\', :template, CAST(:payload AS jsonb), \'queued\')',
            [
                'id' => Uuid::v4(),
                'org' => $organizationId,
                'account' => $accountId,
                'template' => $template,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function notifyManagers(string $organizationId, string $template, array $payload): void
    {
        $rows = $this->database->fetchAll(
            'SELECT account_id FROM membership
             WHERE organization_id = :org AND status = \'active\' AND \'manager\' = ANY(roles)',
            ['org' => $organizationId],
        );
        foreach ($rows as $row) {
            $this->notify((string) $row['account_id'], $organizationId, $template, $payload);
        }
    }
}
