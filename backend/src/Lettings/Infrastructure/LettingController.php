<?php

declare(strict_types=1);

namespace App\Lettings\Infrastructure;

use App\Lettings\Application\LeaseQuery;
use App\Lettings\Application\ListingService;
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

final class LettingController
{
    public function __construct(
        private readonly ListingService $listings,
        private readonly LeaseQuery $leases,
        private readonly CommandGate $gate,
        private readonly IdempotencyStore $idempotency,
        private readonly RequestContext $context,
        private readonly ApiResponder $responder,
        private readonly JsonBody $json,
    ) {
    }

    #[Route('/api/v1/listings', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request): JsonResponse {
            $data = $this->listings->create($actor, $this->json->object($request));

            return $this->responder->ok($data, 201, $data['version']);
        });
    }

    #[Route('/api/v1/listings/{id}/publish', methods: ['POST'])]
    public function publish(Request $request, string $id): JsonResponse
    {
        return $this->gate->run($request, ['manager'], function ($actor) use ($request, $id): JsonResponse {
            $this->assertUuid($id);
            $data = $this->listings->publish($actor, $id, $this->json->object($request));

            return $this->responder->ok($data, 200, $data['version']);
        });
    }

    #[Route('/api/v1/public/listings/{slug}', methods: ['GET'])]
    public function publicShow(string $slug): JsonResponse
    {
        return $this->responder->ok($this->listings->publicCard($slug));
    }

    #[Route('/api/v1/public/listings/{slug}/visit-requests', methods: ['POST'])]
    public function visitRequest(Request $request, string $slug): JsonResponse
    {
        $organizationId = $this->listings->organizationOfPublishedSlug($slug);

        return $this->idempotency->run($request, $organizationId, function () use ($request, $slug): JsonResponse {
            return $this->responder->ok($this->listings->requestVisit($slug, $this->json->object($request)), 201);
        });
    }

    #[Route('/api/v1/leases/{id}', methods: ['GET'])]
    public function lease(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager', 'owner', 'renter']);
        $this->assertUuid($id);
        $data = $this->leases->get($actor, $id);

        return $this->responder->ok($data, 200, $data['version']);
    }

    #[Route('/api/v1/leases/{id}/rent-schedule', methods: ['GET'])]
    public function schedule(string $id): JsonResponse
    {
        $actor = $this->context->requireMembership(['manager', 'owner', 'renter']);
        $this->assertUuid($id);

        return $this->responder->ok($this->leases->schedule($actor, $id));
    }

    private function assertUuid(string $id): void
    {
        if (!Uuid::is($id)) {
            throw new ApiException(404, 'NOT_FOUND', 'Ressource introuvable.');
        }
    }
}
