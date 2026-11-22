<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\SharedKernel\Http\ApiResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(private readonly ApiResponder $responder)
    {
    }

    #[Route('/api/health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->responder->ok([
            'status' => 'ok',
            'service' => 'veille',
        ]);
    }
}
