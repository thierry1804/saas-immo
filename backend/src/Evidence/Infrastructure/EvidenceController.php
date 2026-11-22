<?php

declare(strict_types=1);

namespace App\Evidence\Infrastructure;

use App\Evidence\Application\MediaService;
use App\Evidence\Application\ReportService;
use App\Evidence\Application\VisitSealService;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Http\CommandGate;
use App\SharedKernel\Http\JsonBody;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Security\RequestContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class EvidenceController
{
    public function __construct(
        private readonly MediaService $media,
        private readonly VisitSealService $visits,
        private readonly ReportService $reports,
        private readonly CommandGate $gate,
        private readonly RequestContext $context,
        private readonly ApiResponder $responder,
        private readonly JsonBody $json,
    ) {
    }

    #[Route('/api/v1/media/uploads', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        return $this->gate->run($request, ['agent', 'manager'], function ($actor) use ($request): JsonResponse {
            return $this->responder->ok($this->media->initUpload($actor, $this->json->object($request)), 201);
        });
    }

    #[Route('/api/v1/media/uploads/{id}/complete', methods: ['POST'])]
    public function complete(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['agent', 'manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);

            return $this->responder->ok($this->media->complete($actor, $id, $this->json->object($request)));
        });
    }

    #[Route('/api/v1/visits/{id}/finalize', methods: ['POST'])]
    public function finalize(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['agent', 'manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);

            return $this->responder->ok($this->visits->finalize($actor, $id, $this->json->object($request)));
        });
    }

    #[Route('/api/v1/visits/{id}/amendments', methods: ['POST'])]
    public function amend(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['agent', 'manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);

            return $this->responder->ok($this->visits->amend($actor, $id, $this->json->object($request)), 201);
        });
    }

    #[Route('/api/v1/missions/{id}/review', methods: ['POST'])]
    public function review(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);
            $data = $this->reports->review($actor, $id, $this->json->object($request));

            return $this->responder->ok($data, 200, $data['version']);
        });
    }

    #[Route('/api/v1/reports/{id}/publish', methods: ['POST'])]
    public function publish(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);

            return $this->responder->ok($this->reports->publish($actor, $id, $this->json->object($request)));
        });
    }

    #[Route('/api/v1/reports/{id}/exports', methods: ['POST'])]
    public function export(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager', 'owner'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);
            $data = $this->reports->export($actor, $id, $this->json->object($request));

            return $this->responder->accepted($data['operation_id'], $data['status_url']);
        });
    }

    #[Route('/api/v1/operations/{id}', methods: ['GET'])]
    public function operation(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership();
        $this->assertUuid($id);

        return $this->responder->ok($this->reports->operation($actor, $id));
    }

    #[Route('/api/v1/verify/{opaqueCode}', methods: ['GET'])]
    public function verify(Request $request, string $opaqueCode): JsonResponse
    {
        if (preg_match('/^[a-f0-9]{32}$/', $opaqueCode) !== 1) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
        $hash = $request->query->get('hash');

        return $this->responder->ok($this->reports->verify($opaqueCode, is_string($hash) ? $hash : null));
    }

    private function assertUuid(string $id): void
    {
        if (!Uuid::is($id)) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
    }
}
