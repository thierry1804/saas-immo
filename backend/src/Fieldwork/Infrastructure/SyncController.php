<?php

declare(strict_types=1);

namespace App\Fieldwork\Infrastructure;

use App\Fieldwork\Application\SyncService;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Http\JsonBody;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Security\RequestContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SyncController
{
    public function __construct(
        private readonly SyncService $sync,
        private readonly RequestContext $context,
        private readonly ApiResponder $responder,
        private readonly JsonBody $json,
    ) {
    }

    #[Route('/api/v1/agent/sync', methods: ['GET'])]
    public function pull(Request $request): JsonResponse
    {
        $actor = $this->context->requireMembership(['agent']);
        $deviceId = (string) $request->query->get('device_id', '');
        if (!Uuid::is($deviceId)) {
            return $this->responder->failure(422, 'UNPROCESSABLE', 'device_id requis.');
        }
        $cursor = $request->query->get('cursor');

        return $this->responder->ok($this->sync->pull($actor, $deviceId, is_string($cursor) && $cursor !== '' ? $cursor : null));
    }

    #[Route('/api/v1/agent/sync/actions', methods: ['POST'])]
    public function push(Request $request): JsonResponse
    {
        $actor = $this->context->requireMembership(['agent']);
        $this->context->assertWritable($actor);

        return $this->responder->ok($this->sync->push($actor, $this->json->object($request)));
    }
}
