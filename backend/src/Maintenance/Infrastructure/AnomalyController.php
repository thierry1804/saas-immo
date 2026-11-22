<?php

declare(strict_types=1);

namespace App\Maintenance\Infrastructure;

use App\Maintenance\Application\AnomalyService;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Http\CommandGate;
use App\SharedKernel\Http\JsonBody;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Security\RequestContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AnomalyController
{
    public function __construct(
        private readonly AnomalyService $anomalies,
        private readonly CommandGate $gate,
        private readonly RequestContext $context,
        private readonly ApiResponder $responder,
        private readonly JsonBody $json,
    ) {
    }

    #[Route('/api/v1/anomalies', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->gate->run($request, ['manager', 'agent', 'owner', 'renter'], function ($actor) use ($request): JsonResponse {
            $data = $this->anomalies->report($actor, $this->json->object($request));

            return $this->responder->ok($data, 201, $data['version']);
        });
    }

    #[Route('/api/v1/anomalies/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager', 'agent', 'owner', 'renter']);
        $this->assertUuid($id);
        $data = $this->anomalies->get($actor->organizationId, $id);

        return $this->responder->ok($data, 200, $data['version']);
    }

    #[Route('/api/v1/anomalies/{id}/quotes', methods: ['POST'])]
    public function quote(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);

            return $this->responder->ok($this->anomalies->quote($actor, $id, $this->json->object($request)), 201);
        });
    }

    private function assertUuid(string $id): void
    {
        if (!Uuid::is($id)) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
    }
}
