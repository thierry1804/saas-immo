<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Ledger\Application\LedgerService;
use App\Payments\Domain\ProviderCatalog;
use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Money\Money;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;

final class PaymentService
{
    public function __construct(
        private readonly Database $database,
        private readonly LedgerService $ledger,
        private readonly Journal $journal,
        private readonly SimulatedPaymentAdapter $adapter,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function initiate(Membership $actor, string $invoiceId, array $body): array
    {
        $invoice = $this->invoice($actor->organizationId, $invoiceId);
        if ($invoice['status'] !== 'issued') {
            throw new ApiException(409, 'DOCUMENT_NOT_READY', 'La facture n’est pas émise.');
        }
        $provider = Input::text($body, 'provider', 40);
        $currency = Input::currency($body['currency'] ?? null);
        $amount = Money::of($currency, Input::minor($body, 'amount_minor'));
        $due = Money::of((string) $invoice['currency'], Input::outMinor($invoice['total_minor']));
        $amount->assertSameCurrency($due);
        if (!$amount->equals($due)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Le montant doit égaler le total de la facture.');
        }
        ProviderCatalog::assert($provider, $currency);
        $method = Input::text($body, 'method', 40);
        $id = Uuid::v4();
        $this->database->execute(
            'INSERT INTO payment_attempt (
                id, organization_id, invoice_id, provider, method, amount_minor, currency, status, idempotency_key
             ) VALUES (
                :id, :org, :invoice, :provider, :method, :amount, :currency, \'pending\', :idem
             )',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'invoice' => $invoiceId,
                'provider' => $provider,
                'method' => $method,
                'amount' => $amount->amountMinor,
                'currency' => $currency,
                'idem' => null,
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'payment_attempt.pending', 'payment_attempt', $id, [
            'provider' => $provider,
            'currency' => $currency,
        ]);

        return [
            'attempt_id' => $id,
            'status' => 'pending',
            'payment_created' => false,
            'amount_minor' => $amount->amountMinor,
            'currency' => $currency,
            'next_action' => $this->adapter->nextAction($id),
        ];
    }

    /**
     * Lecture seule. N’écrit ni payment ni ledger_entry.
     *
     * @return array<string, mixed>
     */
    public function browserReturn(string $organizationId, string $attemptId): array
    {
        $row = $this->database->fetch(
            'SELECT id, status FROM payment_attempt WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $attemptId],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return [
            'attempt_id' => $row['id'],
            'attempt_status' => $row['status'],
            'settled_by_this_call' => false,
            'message' => 'Le retour navigateur ne solde pas un paiement.',
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function confirm(string $organizationId, string $attemptId, array $body): array
    {
        $attempt = $this->database->fetch(
            'SELECT id, invoice_id, provider, amount_minor, currency, status
             FROM payment_attempt WHERE organization_id = :org AND id = :id FOR UPDATE',
            ['org' => $organizationId, 'id' => $attemptId],
        );
        if ($attempt === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $existing = $this->database->fetch(
            'SELECT id, external_transaction_id, confirmed_at FROM payment WHERE organization_id = :org AND attempt_id = :attempt',
            ['org' => $organizationId, 'attempt' => $attemptId],
        );
        if ($existing !== null || $attempt['status'] === 'confirmed') {
            $ledger = $this->database->fetch(
                'SELECT id FROM ledger_entry WHERE organization_id = :org AND source_type = \'payment\' AND source_id = :id',
                ['org' => $organizationId, 'id' => $existing['id'] ?? ''],
            );

            return [
                'payment_id' => $existing['id'] ?? null,
                'attempt_id' => $attemptId,
                'status' => 'confirmed',
                'ledger_entry_id' => $ledger['id'] ?? null,
                'external_transaction_id' => $existing['external_transaction_id'] ?? null,
            ];
        }
        $outcome = $body['outcome'] ?? 'succeeded';
        if ($outcome !== 'succeeded') {
            $this->database->execute(
                'UPDATE payment_attempt SET status = \'failed\', updated_at = now() WHERE organization_id = :org AND id = :id',
                ['org' => $organizationId, 'id' => $attemptId],
            );

            return ['attempt_id' => $attemptId, 'status' => 'failed', 'payment_id' => null];
        }
        $external = isset($body['external_transaction_id']) && is_string($body['external_transaction_id']) && $body['external_transaction_id'] !== ''
            ? $body['external_transaction_id']
            : 'sim_'.$attemptId;
        $paymentId = Uuid::v4();
        $this->database->execute(
            'INSERT INTO payment (
                id, organization_id, attempt_id, invoice_id, provider, external_transaction_id,
                amount_minor, currency, confirmed_at, confirmation_source
             ) VALUES (
                :id, :org, :attempt, :invoice, :provider, :external, :amount, :currency, now(), \'simulated_adapter\'
             )',
            [
                'id' => $paymentId,
                'org' => $organizationId,
                'attempt' => $attemptId,
                'invoice' => $attempt['invoice_id'],
                'provider' => $attempt['provider'],
                'external' => $external,
                'amount' => $attempt['amount_minor'],
                'currency' => $attempt['currency'],
            ],
        );
        $this->database->execute(
            'UPDATE payment_attempt SET status = \'confirmed\', external_ref = :external, updated_at = now()
             WHERE organization_id = :org AND id = :id',
            ['external' => $external, 'org' => $organizationId, 'id' => $attemptId],
        );
        $amount = Input::outMinor($attempt['amount_minor']);
        $entry = $this->ledger->post(null, $organizationId, [
            'currency' => $attempt['currency'],
            'justification' => 'Confirmation adaptateur simulé',
            'source_type' => 'payment',
            'source_id' => $paymentId,
            'lines' => [
                ['account' => 'tresorerie_disponible', 'debit_minor' => $amount, 'credit_minor' => '0'],
                ['account' => 'revenu_prestation', 'debit_minor' => '0', 'credit_minor' => $amount],
            ],
        ]);
        $this->journal->audit($organizationId, null, 'payment.confirmed', 'payment', $paymentId, [
            'attempt_id' => $attemptId,
            'confirmation_source' => 'simulated_adapter',
            'currency' => $attempt['currency'],
        ]);

        return [
            'payment_id' => $paymentId,
            'attempt_id' => $attemptId,
            'status' => 'confirmed',
            'ledger_entry_id' => $entry['id'],
            'external_transaction_id' => $external,
            'currency' => $attempt['currency'],
            'amount_minor' => $amount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoice(string $organizationId, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, status, currency, total_minor FROM invoice WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return $row;
    }
}
