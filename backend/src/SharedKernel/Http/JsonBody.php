<?php

declare(strict_types=1);

namespace App\SharedKernel\Http;

use Symfony\Component\HttpFoundation\Request;

final class JsonBody
{
    /**
     * @return array<string, mixed>
     */
    public function object(Request $request): array
    {
        $raw = $request->getContent();
        if (trim($raw) === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'BAD_REQUEST', 'JSON illisible.');
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new ApiException(400, 'BAD_REQUEST', 'Un objet JSON est requis.');
        }

        return $decoded;
    }
}
