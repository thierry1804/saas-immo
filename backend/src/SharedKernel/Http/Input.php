<?php

declare(strict_types=1);

namespace App\SharedKernel\Http;

use App\SharedKernel\Ids\Uuid;

final class Input
{
    /**
     * @param array<string, mixed> $body
     */
    public static function text(array $body, string $field, int $max = 2000): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new ApiException(422, 'UNPROCESSABLE', 'Champ requis.', false, [$field => ['Texte requis.']]);
        }
        $value = trim($value);
        if (strlen($value) > $max) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Champ trop long.', false, [$field => ['Trop long.']]);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function optionalText(array $body, string $field, int $max = 2000): ?string
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        return self::text($body, $field, $max);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function uuid(array $body, string $field): string
    {
        $value = $body[$field] ?? null;
        if (!Uuid::is($value)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Identifiant invalide.', false, [$field => ['UUID requis.']]);
        }

        return strtolower((string) $value);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function int(array $body, string $field): int
    {
        $value = $body[$field] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        throw new ApiException(422, 'UNPROCESSABLE', 'Entier requis.', false, [$field => ['Entier requis.']]);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function minor(array $body, string $field = 'amount_minor'): string
    {
        if (!array_key_exists($field, $body)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Montant requis.', false, [$field => ['Entier requis.']]);
        }

        return self::minorValue($body[$field], $field);
    }

    public static function minorValue(mixed $value, string $field = 'amount_minor'): string
    {
        if (is_int($value) && $value >= 0) {
            return (string) $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $normalized = ltrim($value, '0');

            return $normalized === '' ? '0' : $normalized;
        }
        throw new ApiException(422, 'UNPROCESSABLE', 'Le montant doit être un entier, sans décimale.', false, [
            $field => ['Entier positif ou nul requis. EUR en centimes, MGA en ariary.'],
        ]);
    }

    public static function currency(mixed $value): string
    {
        if ($value !== 'EUR' && $value !== 'MGA') {
            throw new ApiException(422, 'CURRENCY_MISMATCH', 'Monnaie explicite requise : EUR ou MGA.', false, [
                'currency' => ['EUR ou MGA.'],
            ]);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function dateTime(array $body, string $field): \DateTimeImmutable
    {
        $value = $body[$field] ?? null;
        if (!is_string($value)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Date requise.', false, [$field => ['Date ISO requise.']]);
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Date illisible.', false, [$field => ['Date ISO requise.']]);
        }
    }

    public static function outMinor(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+\.0+$/', $value) === 1) {
            return substr($value, 0, (int) strpos($value, '.'));
        }

        return (string) $value;
    }
}
