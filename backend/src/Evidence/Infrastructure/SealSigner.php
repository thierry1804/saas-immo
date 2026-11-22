<?php

declare(strict_types=1);

namespace App\Evidence\Infrastructure;

final class SealSigner
{
    public function __construct(private readonly string $appSecret)
    {
    }

    /**
     * @return array{signature: string, key_id: string}
     */
    public function sign(string $payload): array
    {
        if (!function_exists('sodium_crypto_sign_seed_keypair')) {
            return [
                'signature' => hash_hmac('sha256', $payload, $this->appSecret),
                'key_id' => 'veille-dev-hmac',
            ];
        }
        $seed = substr(hash('sha256', $this->appSecret.':evidence-seal', true), 0, SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $signature = sodium_bin2base64(
            sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($keypair)),
            SODIUM_BASE64_VARIANT_ORIGINAL,
        );

        return ['signature' => $signature, 'key_id' => 'veille-dev-ed25519'];
    }
}
