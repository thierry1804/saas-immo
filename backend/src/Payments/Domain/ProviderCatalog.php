<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\SharedKernel\Http\ApiException;

final class ProviderCatalog
{
    /** @var array<string, string|null> */
    private const CURRENCY = [
        'card_eur' => 'EUR',
        'mvola' => 'MGA',
        'orange_money' => 'MGA',
        'airtel_money' => 'MGA',
        'manual' => null,
    ];

    public static function assert(string $provider, string $currency): void
    {
        if (!array_key_exists($provider, self::CURRENCY)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Prestataire simulé inconnu.');
        }
        $expected = self::CURRENCY[$provider];
        if ($expected !== null && $expected !== $currency) {
            throw new ApiException(422, 'CURRENCY_MISMATCH', 'Ce prestataire simulé n’accepte pas cette monnaie.');
        }
    }
}
