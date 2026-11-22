<?php

declare(strict_types=1);

namespace App\SharedKernel;

use App\SharedKernel\Http\ApiException;

final class JsonCanon
{
    public static function hash(mixed $value): string
    {
        return hash('sha256', self::encode($value));
    }

    public static function encode(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === [] || array_is_list($value)) {
                $items = [];
                foreach ($value as $item) {
                    $items[] = self::encode($item);
                }

                return '['.implode(',', $items).']';
            }
            ksort($value);
            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = json_encode((string) $key, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).':'.self::encode($item);
            }

            return '{'.implode(',', $parts).'}';
        }
        if (is_float($value)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Les nombres décimaux sont refusés.');
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
