<?php

declare(strict_types=1);

namespace App\Referrals\Infrastructure;

use App\Referrals\Application\CommissionQuery;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Security\RequestContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CommissionController
{
    public function __construct(
        private readonly CommissionQuery $commissions,
        private readonly RequestContext $context,
        private readonly ApiResponder $responder,
    ) {
    }

    #[Route('/api/v1/commissions', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager', 'referrer']);

        return $this->responder->ok($this->commissions->list($actor));
    }

    #[Route('/api/v1/commissions/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager', 'referrer']);
        if (!Uuid::is($id)) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }

        return $this->responder->ok($this->commissions->get($actor, $id));
    }
}
