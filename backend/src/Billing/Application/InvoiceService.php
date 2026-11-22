<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Money\Money;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class InvoiceService
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
    public function draft(Membership $actor, array $body): array
    {
        $propertyId = Input::uuid($body, 'property_id');
        $property = $this->database->fetch(
            'SELECT id, current_owner_id FROM property WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $propertyId],
        );
        if ($property === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $tariff = $this->database->fetch(
            'SELECT amount_minor, currency FROM tariff_version
             WHERE organization_id = :org AND property_id = :property AND type = \'visit\'
               AND (valid_to IS NULL OR valid_to >= CURRENT_DATE)
             ORDER BY valid_from DESC LIMIT 1',
            ['org' => $actor->organizationId, 'property' => $propertyId],
        );
        if ($tariff === null) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Aucun tarif de visite pour ce logement.');
        }
        $money = Money::of((string) $tariff['currency'], Input::outMinor($tariff['amount_minor']));
        if (isset($body['currency'])) {
            $money->assertSameCurrency(Money::of(Input::currency($body['currency']), '0'));
        }
        if (isset($body['amount_minor'])) {
            $asked = Money::of($money->currency, Input::minor($body, 'amount_minor'));
            if (!$asked->equals($money)) {
                throw new ApiException(422, 'UNPROCESSABLE', 'Le montant doit reprendre le tarif de visite, sans saisie libre.');
            }
        }
        $id = Uuid::v4();
        $lines = [[
            'label' => 'Visite',
            'amount_minor' => $money->amountMinor,
            'currency' => $money->currency,
        ]];
        $sourceId = isset($body['source_id']) ? Input::uuid($body, 'source_id') : $propertyId;
        $this->database->execute(
            'INSERT INTO invoice (
                id, organization_id, client_owner_id, currency, status, source_type, source_id, total_minor, lines
             ) VALUES (
                :id, :org, :owner, :currency, \'draft\', \'visit_tariff\', :source, :total, CAST(:lines AS jsonb)
             )',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'owner' => $property['current_owner_id'],
                'currency' => $money->currency,
                'source' => $sourceId,
                'total' => $money->amountMinor,
                'lines' => json_encode($lines, JSON_THROW_ON_ERROR),
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'invoice.drafted', 'invoice', $id, [
            'currency' => $money->currency,
            'total_minor' => $money->amountMinor,
        ]);

        return $this->present($actor->organizationId, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function issue(Membership $actor, string $id): array
    {
        $row = $this->require($actor->organizationId, $id);
        if ($row['status'] === 'issued') {
            return $this->presentRow($row);
        }
        $seq = (int) $this->database->fetchOne(
            'INSERT INTO document_sequence (organization_id, series, last_value)
             VALUES (:org, \'invoice\', 1)
             ON CONFLICT (organization_id, series)
             DO UPDATE SET last_value = document_sequence.last_value + 1
             RETURNING last_value',
            ['org' => $actor->organizationId],
        );
        $number = sprintf('FAC-%s-%04d', gmdate('Y'), $seq);
        $this->database->execute(
            'UPDATE invoice
             SET status = \'issued\', number = :number, issued_at = now(), version = version + 1, updated_at = now()
             WHERE organization_id = :org AND id = :id AND status = \'draft\'',
            ['number' => $number, 'org' => $actor->organizationId, 'id' => $id],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'invoice.issued', 'invoice', $id, [
            'number' => $number,
        ]);

        return $this->present($actor->organizationId, $id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function credit(Membership $actor, string $id, array $body): array
    {
        $invoice = $this->require($actor->organizationId, $id);
        if ($invoice['status'] !== 'issued') {
            throw new ApiException(409, 'DOCUMENT_NOT_READY', 'La facture n’est pas émise.');
        }
        $reason = Input::text($body, 'reason', 2000);
        $amount = Money::of((string) $invoice['currency'], Input::minor($body, 'amount_minor'));
        $invoiceMoney = Money::of((string) $invoice['currency'], Input::outMinor($invoice['total_minor']));
        $amount->assertSameCurrency($invoiceMoney);
        if ((int) $amount->amountMinor > (int) $invoiceMoney->amountMinor) {
            throw new ApiException(422, 'UNPROCESSABLE', 'L’avoir dépasse le total de la facture.');
        }
        $seq = (int) $this->database->fetchOne(
            'INSERT INTO document_sequence (organization_id, series, last_value)
             VALUES (:org, \'credit_note\', 1)
             ON CONFLICT (organization_id, series)
             DO UPDATE SET last_value = document_sequence.last_value + 1
             RETURNING last_value',
            ['org' => $actor->organizationId],
        );
        $noteId = Uuid::v4();
        $number = sprintf('AV-%s-%04d', gmdate('Y'), $seq);
        $this->database->execute(
            'INSERT INTO credit_note (id, organization_id, invoice_id, number, amount_minor, currency, reason, author_id, lines)
             VALUES (:id, :org, :invoice, :number, :amount, :currency, :reason, :author, CAST(:lines AS jsonb))',
            [
                'id' => $noteId,
                'org' => $actor->organizationId,
                'invoice' => $id,
                'number' => $number,
                'amount' => $amount->amountMinor,
                'currency' => $amount->currency,
                'reason' => $reason,
                'author' => $actor->accountId,
                'lines' => json_encode([['amount_minor' => $amount->amountMinor, 'currency' => $amount->currency]], JSON_THROW_ON_ERROR),
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'invoice.credited', 'credit_note', $noteId, [
            'reason' => $reason,
            'invoice_id' => $id,
            'amount_minor' => $amount->amountMinor,
            'currency' => $amount->currency,
        ]);

        return [
            'id' => $noteId,
            'invoice_id' => $id,
            'number' => $number,
            'amount_minor' => $amount->amountMinor,
            'currency' => $amount->currency,
            'invoice_total_minor' => Input::outMinor($invoice['total_minor']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(string $organizationId, string $id): array
    {
        return $this->presentRow($this->require($organizationId, $id));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentRow(array $row): array
    {
        return [
            'id' => $row['id'],
            'number' => $row['number'],
            'status' => $row['status'],
            'currency' => $row['currency'],
            'total_minor' => Input::outMinor($row['total_minor']),
            'source_type' => $row['source_type'],
            'source_id' => $row['source_id'],
            'version' => (int) $row['version'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function require(string $organizationId, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, number, status, currency, total_minor, source_type, source_id, version
             FROM invoice WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return $row;
    }
}
