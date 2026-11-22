<?php

declare(strict_types=1);

namespace App\SharedKernel\Security;

use App\SharedKernel\Http\ApiException;

final class AccessTokenCodec
{
    public function __construct(private readonly string $appSecret)
    {
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function issue(array $claims): string
    {
        $header = $this->encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = $this->encode($claims);
        $signature = $this->sign($header.'.'.$payload);

        return $header.'.'.$payload.'.'.$signature;
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $token, string $expectedType): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Session absente ou expirée.');
        }
        [$header, $payload, $signature] = $parts;
        $expected = $this->sign($header.'.'.$payload);
        if (!hash_equals($expected, $signature)) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Session absente ou expirée.');
        }
        $claims = json_decode($this->decode($payload), true);
        if (!is_array($claims) || ($claims['typ'] ?? null) !== $expectedType) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Session absente ou expirée.');
        }
        $exp = $claims['exp'] ?? 0;
        if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
            throw new ApiException(401, 'SESSION_EXPIRED', 'Session expirée.');
        }
        if ((int) $exp < time()) {
            throw new ApiException(401, 'SESSION_EXPIRED', 'Session expirée.');
        }
        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new ApiException(401, 'UNAUTHORIZED', 'Session absente ou expirée.');
        }

        return $claims;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encode(array $value): string
    {
        return rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode(strtr($value, '-_', '+/'), true);
        if ($raw === false) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Session absente ou expirée.');
        }

        return $raw;
    }

    private function sign(string $data): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, $this->appSecret, true)), '+/', '-_'), '=');
    }
}
