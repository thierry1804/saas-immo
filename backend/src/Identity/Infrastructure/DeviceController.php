<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Application\DirectoryService;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Http\CommandGate;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Http\JsonBody;
use App\SharedKernel\Ids\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class DeviceController
{
    public function __construct(
        private readonly DirectoryService $directory,
        private readonly CommandGate $gate,
        private readonly ApiResponder $responder,
        private readonly JsonBody $json,
    ) {
    }

    #[Route('/api/v1/devices', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        return $this->gate->run($request, [], function ($actor) use ($request): JsonResponse {
            $data = $this->directory->registerDevice($actor, $this->json->object($request));

            return $this->responder->ok($data, $data['created'] ? 201 : 200);
        });
    }

    #[Route('/api/v1/devices/{id}/pin', methods: ['POST'])]
    public function pin(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, [], function ($actor) use ($request, $id): JsonResponse {
            if (!Uuid::is($id)) {
                return $this->responder->failure(404, 'NOT_FOUND', 'Ressource introuvable.');
            }
            $body = $this->json->object($request);

            return $this->responder->ok($this->directory->setPin($actor, $id, Input::text($body, 'pin', 6)));
        });
    }

    #[Route('/api/v1/devices/{id}/revoke', methods: ['POST'])]
    public function revoke(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, [], function ($actor) use ($request, $id): JsonResponse {
            if (!Uuid::is($id)) {
                return $this->responder->failure(404, 'NOT_FOUND', 'Ressource introuvable.');
            }
            $body = $this->json->object($request);

            return $this->responder->ok($this->directory->revoke($actor, $id, Input::text($body, 'reason', 500)));
        });
    }

    #[Route('/api/v1/organizations/{id}/memberships', methods: ['POST'])]
    public function invite(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            if (!Uuid::is($id)) {
                return $this->responder->failure(404, 'NOT_FOUND', 'Ressource introuvable.');
            }

            return $this->responder->ok($this->directory->invite($actor, $id, $this->json->object($request)), 201);
        });
    }
}
