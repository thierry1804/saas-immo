<?php

declare(strict_types=1);

namespace App\SharedKernel\Money;

use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;

final readonly class Money
{
    public function __construct(
        public string $currency,
        public string $amountMinor,
    ) {
        if ($currency !== 'EUR' && $currency !== 'MGA') {
            throw new ApiException(422, 'CURRENCY_MISMATCH', 'Monnaie explicite requise : EUR ou MGA.');
        }
        if (preg_match('/^\d+$/', $amountMinor) !== 1) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Montant entier requis.');
        }
    }

    public static function of(string $currency, string $amountMinor): self
    {
        return new self(Input::currency($currency), Input::minorValue($amountMinor));
    }

    public function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new ApiException(422, 'CURRENCY_MISMATCH', 'Impossible d’additionner ou de rapprocher EUR et MGA.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amountMinor === $other->amountMinor;
    }
}
