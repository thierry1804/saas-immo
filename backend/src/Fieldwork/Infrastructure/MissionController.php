<?php

declare(strict_types=1);

namespace App\Fieldwork\Infrastructure;

use App\Fieldwork\Application\MissionService;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Http\CommandGate;
use App\SharedKernel\Http\JsonBody;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Security\RequestContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class MissionController
{
    public function __construct(
        private readonly MissionService $missions,
        private readonly CommandGate $gate,
        private readonly RequestContext $context,
        private readonly ApiResponder $responder,
        private readonly JsonBody $json,
    ) {
    }

    #[Route('/api/v1/missions', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request): JsonResponse {
            $data = $this->missions->create($actor, $this->json->object($request));

            return $this->responder->ok($data, 201, $data['version']);
        });
    }

    #[Route('/api/v1/missions/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager', 'agent']);
        $this->assertUuid($id);
        $data = $this->missions->getFor($actor, $id);

        return $this->responder->ok($data, 200, $data['version']);
    }

    #[Route('/api/v1/missions/{id}/reassign', methods: ['POST'])]
    public function reassign(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);
            $data = $this->missions->reassign($actor, $id, $this->json->object($request));

            return $this->responder->ok($data, 200, $data['version']);
        });
    }

    #[Route('/api/v1/missions/{id}/cancel', methods: ['POST'])]
    public function cancel(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, 'cancelled');
    }

    #[Route('/api/v1/missions/{id}/interrupt', methods: ['POST'])]
    public function interrupt(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, 'interrupted');
    }

    #[Route('/api/v1/recurrences', methods: ['POST'])]
    public function recurrence(Request $request): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request): JsonResponse {
            $data = $this->missions->createRecurrence($actor, $this->json->object($request));

            return $this->responder->ok($data, 201, 1);
        });
    }

    private function transition(Request $request, string $id, string $status): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id, $status): JsonResponse {
            $this->assertUuid($id);
            $data = $this->missions->transition($actor, $id, $status, $this->json->object($request));

            return $this->responder->ok($data, 200, $data['version']);
        });
    }

    private function assertUuid(string $id): void
    {
        if (!Uuid::is($id)) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
    }

}
