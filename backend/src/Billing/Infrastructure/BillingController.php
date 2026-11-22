<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure;

use App\Billing\Application\InvoiceService;
use App\Ledger\Application\LedgerService;
use App\Payments\Application\PaymentService;
use App\Payments\Application\SimulatedPaymentAdapter;
use App\Payouts\Application\PayoutService;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Http\CommandGate;
use App\SharedKernel\Http\JsonBody;
use App\SharedKernel\Idempotency\IdempotencyStore;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Security\RequestContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class BillingController
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly PaymentService $payments,
        private readonly SimulatedPaymentAdapter $adapter,
        private readonly LedgerService $ledger,
        private readonly PayoutService $payouts,
        private readonly CommandGate $gate,
        private readonly IdempotencyStore $idempotency,
        private readonly RequestContext $context,
        private readonly ApiResponder $responder,
        private readonly JsonBody $json,
    ) {
    }

    #[Route('/api/v1/invoices/drafts', methods: ['POST'])]
    public function draft(Request $request): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request): JsonResponse {
            $data = $this->invoices->draft($actor, $this->json->object($request));

            return $this->responder->ok($data, 201, $data['version']);
        });
    }

    #[Route('/api/v1/invoices/{id}/issue', methods: ['POST'])]
    public function issue(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($id): JsonResponse {
            $this->assertUuid($id);
            $data = $this->invoices->issue($actor, $id);

            return $this->responder->ok($data, 200, $data['version']);
        });
    }

    #[Route('/api/v1/invoices/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager']);
        $this->assertUuid($id);
        $data = $this->invoices->present($actor->organizationId, $id);

        return $this->responder->ok($data, 200, $data['version']);
    }

    #[Route('/api/v1/invoices/{id}/credit-notes', methods: ['POST'])]
    public function credit(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $actor->requirePermission('finance_prepare');
            $this->assertUuid($id);

            return $this->responder->ok($this->invoices->credit($actor, $id, $this->json->object($request)), 201);
        });
    }

    #[Route('/api/v1/invoices/{id}/payment-attempts', methods: ['POST'])]
    public function attempt(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager', 'owner', 'renter'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);

            return $this->responder->ok($this->payments->initiate($actor, $id, $this->json->object($request)), 201);
        });
    }

    #[Route('/api/v1/payment-attempts/{id}/browser-return', methods: ['POST'])]
    public function browserReturn(string $id): JsonResponse
    {
        $this->assertUuid($id);
        $org = $this->context->organizationHeader();

        return $this->responder->ok($this->payments->browserReturn($org, $id));
    }

    #[Route('/api/v1/payment-attempts/{id}/provider-confirm', methods: ['POST'])]
    public function confirm(Request $request, string $id): JsonResponse
    {
        $this->adapter->assertServerConfirmation($request);
        $this->assertUuid($id);
        $org = $this->context->organizationHeader();

        return $this->idempotency->run($request, $org, function () use ($request, $org, $id): JsonResponse {
            return $this->responder->ok($this->payments->confirm($org, $id, $this->json->object($request)));
        });
    }

    #[Route('/api/v1/ledger/entries', methods: ['POST'])]
    public function postLedger(Request $request): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request): JsonResponse {
            $actor->requirePermission('finance_prepare');

            return $this->responder->ok($this->ledger->post($actor->accountId, $actor->organizationId, $this->json->object($request)), 201);
        });
    }

    #[Route('/api/v1/ledger/entries/{id}', methods: ['GET'])]
    public function showLedger(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager']);
        if (!$actor->hasPermission('finance_prepare') && !$actor->hasPermission('finance_approve')) {
            throw new ApiException(403, 'FORBIDDEN', 'Action interdite pour ce compte.');
        }
        $this->assertUuid($id);

        return $this->responder->ok($this->ledger->get($actor->organizationId, $id));
    }

    #[Route('/api/v1/payout-batches', methods: ['POST'])]
    public function preparePayout(Request $request): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request): JsonResponse {
            $data = $this->payouts->prepare($actor, $this->json->object($request));

            return $this->responder->ok($data, 201, $data['version']);
        });
    }

    #[Route('/api/v1/payout-batches/{id}/approve', methods: ['POST'])]
    public function approvePayout(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);
            $data = $this->payouts->approve($actor, $id, $this->json->object($request));

            return $this->responder->ok($data, 200, $data['version']);
        });
    }

    #[Route('/api/v1/payout-batches/{id}', methods: ['GET'])]
    public function showPayout(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager']);
        $this->assertUuid($id);
        $data = $this->payouts->get($actor->organizationId, $id);

        return $this->responder->ok($data, 200, $data['version']);
    }

    private function assertUuid(string $id): void
    {
        if (!Uuid::is($id)) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
    }
}
