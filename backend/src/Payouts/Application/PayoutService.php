<?php

declare(strict_types=1);

namespace App\Payouts\Application;

use App\Payouts\Domain\SelfApproval;
use App\SharedKernel\Audit\Journal;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Money\Money;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;
use Doctrine\DBAL\Exception\DriverException;

final class PayoutService
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
    public function prepare(Membership $actor, array $body): array
    {
        $actor->requirePermission('prepare_payout');
        $beneficiaryId = Input::uuid($body, 'beneficiary_id');
        $beneficiary = $this->database->fetch(
            'SELECT id, currency, status FROM beneficiary WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $beneficiaryId],
        );
        if ($beneficiary === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        if ($beneficiary['status'] !== 'approved') {
            throw new ApiException(409, 'BENEFICIARY_NOT_APPROVED', 'Le bénéficiaire n’est pas approuvé.');
        }
        $money = Money::of(Input::currency($body['currency'] ?? null), Input::minor($body, 'amount_minor'));
        if ($money->currency !== $beneficiary['currency']) {
            throw new ApiException(422, 'CURRENCY_MISMATCH', 'La monnaie du lot doit être celle du bénéficiaire.');
        }
        $reason = Input::text($body, 'reason', 2000);
        $id = Uuid::v4();
        $this->database->execute(
            'INSERT INTO payout_batch (
                id, organization_id, beneficiary_id, currency, amount_minor, status,
                prepared_by, prepared_at, preparation_reason
             ) VALUES (
                :id, :org, :beneficiary, :currency, :amount, \'prepared\', :author, now(), :reason
             )',
            [
                'id' => $id,
                'org' => $actor->organizationId,
                'beneficiary' => $beneficiaryId,
                'currency' => $money->currency,
                'amount' => $money->amountMinor,
                'author' => $actor->accountId,
                'reason' => $reason,
            ],
        );
        $this->journal->audit($actor->organizationId, $actor->accountId, 'payout.prepared', 'payout_batch', $id, [
            'reason' => $reason,
            'amount_minor' => $money->amountMinor,
            'currency' => $money->currency,
        ]);

        return $this->get($actor->organizationId, $id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function approve(Membership $actor, string $id, array $body): array
    {
        $batch = $this->database->fetch(
            'SELECT id, prepared_by, status, version FROM payout_batch WHERE organization_id = :org AND id = :id',
            ['org' => $actor->organizationId, 'id' => $id],
        );
        if ($batch === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        SelfApproval::forbid((string) $batch['prepared_by'], $actor->accountId);
        $actor->requirePermission('approve_payout');
        $actor->requireRecentAuth();
        $reason = Input::text($body, 'reason', 2000);
        if ($batch['status'] === 'approved') {
            return $this->get($actor->organizationId, $id);
        }
        try {
            $updated = $this->database->execute(
                'UPDATE payout_batch
                 SET status = \'approved\', approved_by = :author, approved_at = now(), approval_reason = :reason,
                     version = version + 1, updated_at = now()
                 WHERE organization_id = :org AND id = :id AND status = \'prepared\'',
                [
                    'author' => $actor->accountId,
                    'reason' => $reason,
                    'org' => $actor->organizationId,
                    'id' => $id,
                ],
            );
        } catch (DriverException $exception) {
            if (str_contains($exception->getMessage(), 'SELF_APPROVAL_FORBIDDEN')) {
                throw new ApiException(403, 'SELF_APPROVAL_FORBIDDEN', 'La personne qui prépare un lot ne peut pas l’approuver.');
            }
            throw $exception;
        }
        if ($updated !== 1) {
            throw new ApiException(409, 'VERSION_CONFLICT', 'Le lot n’est plus approuvable.');
        }
        $this->journal->audit($actor->organizationId, $actor->accountId, 'payout.approved', 'payout_batch', $id, [
            'reason' => $reason,
        ]);

        return $this->get($actor->organizationId, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $organizationId, string $id): array
    {
        $row = $this->database->fetch(
            'SELECT id, beneficiary_id, currency, amount_minor, status, prepared_by, prepared_at, preparation_reason,
                    approved_by, approved_at, approval_reason, version
             FROM payout_batch WHERE organization_id = :org AND id = :id',
            ['org' => $organizationId, 'id' => $id],
        );
        if ($row === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return [
            'id' => $row['id'],
            'beneficiary_id' => $row['beneficiary_id'],
            'currency' => $row['currency'],
            'amount_minor' => Input::outMinor($row['amount_minor']),
            'status' => $row['status'],
            'prepared_by' => $row['prepared_by'],
            'prepared_at' => $row['prepared_at'],
            'preparation_reason' => $row['preparation_reason'],
            'approved_by' => $row['approved_by'],
            'approved_at' => $row['approved_at'],
            'approval_reason' => $row['approval_reason'],
            'version' => (int) $row['version'],
        ];
    }
}
