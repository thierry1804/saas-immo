<?php

declare(strict_types=1);

namespace App\SharedKernel\Http;

use App\SharedKernel\Idempotency\IdempotencyStore;
use App\SharedKernel\Security\Membership;
use App\SharedKernel\Security\RequestContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CommandGate
{
    public function __construct(
        private readonly RequestContext $context,
        private readonly IdempotencyStore $idempotency,
    ) {
    }

    /**
     * @param list<string> $roles
     * @param callable(Membership): JsonResponse $action
     */
    public function run(Request $request, array $roles, callable $action, bool $write = true): JsonResponse
    {
        $membership = $this->context->requireMembership($roles);
        if ($write) {
            $this->context->assertWritable($membership);
        }

        return $this->idempotency->run(
            $request,
            $membership->organizationId,
            static fn (): JsonResponse => $action($membership),
        );
    }
}
