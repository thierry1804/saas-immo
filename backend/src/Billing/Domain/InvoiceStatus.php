<?php

declare(strict_types=1);

namespace App\Billing\Domain;

final class InvoiceStatus
{
    public const DRAFT = 'draft';
    public const ISSUED = 'issued';
}
