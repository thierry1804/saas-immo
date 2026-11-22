<?php

declare(strict_types=1);

namespace App\SharedKernel\Idempotency;

use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class IdempotencyStore
{
    public function __construct(
        private readonly Database $database,
        private readonly ApiResponder $responder,
    ) {
    }

    /**
     * @param callable(): JsonResponse $action
     */
    public function run(Request $request, string $organizationId, callable $action): JsonResponse
    {
        $key = $request->headers->get('Idempotency-Key');
        if (!Uuid::is($key)) {
            throw new ApiException(400, 'BAD_REQUEST', 'En-tête Idempotency-Key (UUID) requis.');
        }
        $key = strtolower((string) $key);
        $hash = hash('sha256', $request->getContent());

        try {
            return $this->database->tx($organizationId, function () use ($organizationId, $key, $hash, $action): JsonResponse {
                $existing = $this->database->fetch(
                    'SELECT request_hash, response_code, response_body
                     FROM idempotency_record
                     WHERE organization_id = :org AND key = :key
                     FOR UPDATE',
                    ['org' => $organizationId, 'key' => $key],
                );
                if ($existing !== null) {
                    return $this->replay($existing, $hash);
                }
                try {
                    $response = $action();
                } catch (ApiException $exception) {
                    $response = $this->responder->error($exception);
                }
                if ($response->getStatusCode() >= 500) {
                    return $response;
                }
                $this->database->execute(
                    'INSERT INTO idempotency_record (
                        id, organization_id, key, request_hash, response_code, response_body, expires_at
                     ) VALUES (
                        :id, :org, :key, :hash, :code, CAST(:body AS jsonb), now() + interval \'30 days\'
                     )',
                    [
                        'id' => Uuid::v4(),
                        'org' => $organizationId,
                        'key' => $key,
                        'hash' => $hash,
                        'code' => $response->getStatusCode(),
                        'body' => $response->getContent(),
                    ],
                );

                return $response;
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->database->fetch(
                'SELECT request_hash, response_code, response_body
                 FROM idempotency_record
                 WHERE organization_id = :org AND key = :key',
                ['org' => $organizationId, 'key' => $key],
            );
            if ($existing === null) {
                throw new ApiException(409, 'IDEMPOTENCY_KEY_REUSED', 'Cette clé est déjà en cours d’utilisation.');
            }

            return $this->replay($existing, $hash);
        }
    }

    /**
     * @param array<string, mixed> $existing
     */
    private function replay(array $existing, string $hash): JsonResponse
    {
        if (!hash_equals((string) $existing['request_hash'], $hash)) {
            throw new ApiException(409, 'IDEMPOTENCY_KEY_REUSED', 'Cette clé a déjà été utilisée avec un autre contenu.');
        }
        $body = $existing['response_body'];
        if (is_array($body)) {
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } else {
            $json = (string) $body;
        }

        return new JsonResponse($json, (int) $existing['response_code'], [], true);
    }
}
