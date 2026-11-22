<?php

declare(strict_types=1);

namespace App\SharedKernel\Http;

final class ApiException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $fieldErrors
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly bool $retryable = false,
        public readonly array $fieldErrors = [],
    ) {
        parent::__construct($message);
    }
}
