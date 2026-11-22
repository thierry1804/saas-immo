<?php

declare(strict_types=1);

namespace App\Ledger\Application;

use App\Ledger\Domain\BalancedEntry;
use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use Doctrine\DBAL\Exception\DriverException;

final class LedgerService
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
    public function post(?string $authorId, string $organizationId, array $body): array
    {
        $currency = Input::currency($body['currency'] ?? null);
        $justification = Input::text($body, 'justification', 2000);
        $lines = $body['lines'] ?? null;
        if (!is_array($lines) || array_is_list($lines) === false) {
            throw new ApiException(422, 'UNBALANCED_ENTRY', 'Lignes d’écriture requises.');
        }
        /** @var list<array<string, mixed>> $lines */
        $normalized = BalancedEntry::normalize($currency, $lines);
        $correction = null;
        if (isset($body['correction_of'])) {
            $correction = Input::uuid($body, 'correction_of');
            $original = $this->database->fetch(
                'SELECT id FROM ledger_entry WHERE organization_id = :org AND id = :id',
                ['org' => $organizationId, 'id' => $correction],
            );
            if ($original === null) {
                throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
            }
        }
        $id = Uuid::v4();
        try {
            $this->database->execute(
                'INSERT INTO ledger_entry (
                    id, organization_id, currency, source_type, source_id, justification, correction_of, author_id, posted_at, lines
                 ) VALUES (
                    :id, :org, :currency, :source_type, :source_id, :justification, :correction, :author, now(), CAST(:lines AS jsonb)
                 )',
                [
                    'id' => $id,
                    'org' => $organizationId,
                    'currency' => $currency,
                    'source_type' => isset($body['source_type']) && is_string($body['source_type']) ? $body['source_type'] : null,
                    'source_id' => isset($body['source_id']) && \App\SharedKernel\Ids\Uuid::is($body['source_id']) ? $body['source_id'] : null,
                    'justification' => $justification,
                    'correction' => $correction,
                    'author' => $authorId,
                    'lines' => json_encode($normalized, JSON_THROW_ON_ERROR),
                ],
            );
        } catch (DriverException $exception) {
            $this->mapDriver($exception);
        }
        $this->journal->audit($organizationId, $authorId, 'ledger.posted', 'ledger_entry', $id, [
            'currency' => $currency,
            'correction_of' => $correction,
        ]);

        return $this->get($organizationId, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $organizationId, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, currency, justification, correction_of, posted_at, lines, source_type, source_id
             FROM ledger_entry WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $lines = $row['lines'];
        if (is_string($lines)) {
            $lines = json_decode($lines, true);
        }

        return [
            'id' => $row['id'],
            'currency' => $row['currency'],
            'justification' => $row['justification'],
            'correction_of' => $row['correction_of'],
            'posted_at' => $row['posted_at'],
            'source_type' => $row['source_type'],
            'source_id' => $row['source_id'],
            'lines' => $lines,
        ];
    }

    private function mapDriver(DriverException $exception): never
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'CURRENCY_MISMATCH')) {
            throw new ApiException(422, 'CURRENCY_MISMATCH', 'Impossible d’additionner EUR et MGA.');
        }
        if (str_contains($message, 'UNBALANCED_ENTRY')) {
            throw new ApiException(422, 'UNBALANCED_ENTRY', 'Écriture déséquilibrée.');
        }
        throw $exception;
    }
}
