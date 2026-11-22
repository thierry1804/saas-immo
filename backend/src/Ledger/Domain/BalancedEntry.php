<?php

declare(strict_types=1);

namespace App\Ledger\Domain;

use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\Input;

final class BalancedEntry
{
    /**
     * @param list<array<string, mixed>> $lines
     * @return list<array{account: string, debit_minor: string, credit_minor: string}>
     */
    public static function normalize(string $currency, array $lines): array
    {
        if (count($lines) < 2) {
            throw new ApiException(422, 'UNBALANCED_ENTRY', 'Une écriture équilibrée contient au moins deux lignes.');
        }
        $debit = 0;
        $credit = 0;
        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                throw new ApiException(422, 'UNBALANCED_ENTRY', 'Ligne d’écriture illisible.');
            }
            if (isset($line['currency']) && $line['currency'] !== $currency) {
                throw new ApiException(422, 'CURRENCY_MISMATCH', 'Impossible d’additionner EUR et MGA.');
            }
            $account = Input::text($line, 'account', 80);
            $d = Input::minor($line, 'debit_minor');
            $c = Input::minor($line, 'credit_minor');
            if ($d !== '0' && $c !== '0') {
                throw new ApiException(422, 'UNBALANCED_ENTRY', 'Une ligne ne porte pas débit et crédit à la fois.');
            }
            $debit += (int) $d;
            $credit += (int) $c;
            $normalized[] = [
                'account' => $account,
                'debit_minor' => $d,
                'credit_minor' => $c,
            ];
        }
        if ($debit !== $credit || $debit === 0) {
            throw new ApiException(422, 'UNBALANCED_ENTRY', 'Écriture déséquilibrée : le total des débits doit égaler le total des crédits.');
        }

        return $normalized;
    }
}
