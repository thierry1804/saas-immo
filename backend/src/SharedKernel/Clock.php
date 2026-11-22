<?php

declare(strict_types=1);

namespace App\SharedKernel;

final class Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function iso(): string
    {
        return $this->now()->format('Y-m-d\TH:i:s\Z');
    }

    public function sql(): string
    {
        return $this->now()->format('Y-m-d H:i:sP');
    }
}
