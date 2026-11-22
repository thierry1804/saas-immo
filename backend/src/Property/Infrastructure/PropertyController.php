<?php

declare(strict_types=1);

namespace App\Property\Infrastructure;

use App\Property\Application\ChecklistService;
use App\Property\Application\PropertyService;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Http\CommandGate;
use App\SharedKernel\Http\JsonBody;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Security\RequestContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PropertyController
{
    public function __construct(
        private readonly PropertyService $properties,
        private readonly ChecklistService $checklists,
        private readonly CommandGate $gate,
        private readonly RequestContext $context,
        private readonly ApiResponder $responder,
        private readonly JsonBody $json,
    ) {
    }

    #[Route('/api/v1/properties', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request): JsonResponse {
            $data = $this->properties->create($actor, $this->json->object($request));

            return $this->responder->ok($data, 201, $data['version']);
        });
    }

    #[Route('/api/v1/properties/{id}', methods: ['PATCH'])]
    public function patch(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);
            $data = $this->properties->patch($actor, $id, $this->json->object($request));

            return $this->responder->ok($data, 200, $data['version']);
        });
    }

    #[Route('/api/v1/properties/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager', 'owner', 'agent']);
        $this->assertUuid($id);
        $data = $this->properties->get($actor->organizationId, $id);

        return $this->responder->ok($data, 200, $data['version']);
    }

    #[Route('/api/v1/checklists/{id}/versions', methods: ['POST'])]
    public function checklistVersion(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);

            return $this->responder->ok($this->checklists->createVersion($actor, $id, $this->json->object($request)), 201, 1);
        });
    }

    #[Route('/api/v1/checklist-versions/{id}/publish', methods: ['POST'])]
    public function publishChecklist(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);
            $data = $this->checklists->publish($actor, $id, $this->json->object($request));

            return $this->responder->ok($data, 200, $data['version']);
        });
    }

    private function assertUuid(string $id): void
    {
        if (!Uuid::is($id)) {
            throw new \App\SharedKernel\Http\ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
    }
}
