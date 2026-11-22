<?php

declare(strict_types=1);

namespace App\SharedKernel\Http;

use App\SharedKernel\Clock;
use App\SharedKernel\Ids\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

final class ApiResponder
{
    public function __construct(
        private readonly RequestStack $requests,
        private readonly Clock $clock,
    ) {
    }

    public function requestId(): string
    {
        $request = $this->requests->getCurrentRequest();
        $existing = $request?->attributes->get('request_id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }
        $id = Uuid::v4();
        $request?->attributes->set('request_id', $id);

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function ok(array $data, int $status = 200, ?int $version = null): JsonResponse
    {
        $meta = [
            'request_id' => $this->requestId(),
            'server_time' => $this->clock->iso(),
        ];
        if ($version !== null) {
            $meta['resource_version'] = $version;
        }

        $response = new JsonResponse(['data' => $data, 'meta' => $meta], $status);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $response;
    }

    public function accepted(string $operationId, string $statusUrl): JsonResponse
    {
        return $this->ok([
            'operation_id' => $operationId,
            'status_url' => $statusUrl,
        ], 202);
    }

    public function error(ApiException $exception): JsonResponse
    {
        $response = new JsonResponse(
            [
                'error' => [
                    'code' => $exception->errorCode,
                    'message' => $exception->getMessage(),
                    'field_errors' => $exception->fieldErrors === [] ? new \stdClass() : $exception->fieldErrors,
                    'retryable' => $exception->retryable,
                    'request_id' => $this->requestId(),
                ],
            ],
            $exception->status,
        );
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $response;
    }

    public function failure(int $status, string $code, string $message, bool $retryable = false): JsonResponse
    {
        return $this->error(new ApiException($status, $code, $message, $retryable));
    }
}
