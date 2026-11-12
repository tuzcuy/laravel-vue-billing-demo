<?php

namespace App\Domain\Billing;

use App\Models\Invoice;

/**
 * Generates sequential, human-readable invoice numbers per year
 * (INV-2026-0001, INV-2026-0002, ...).
 *
 * Reads the highest existing number instead of counting rows, so deleting
 * a draft can never cause a duplicate. Not safe under heavy concurrent
 * writes — see "What I'd improve next" in the README.
 */
class InvoiceNumbers
{
    public function next(): string
    {
        $prefix = sprintf('INV-%d-', now()->year);

        $last = Invoice::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 1 : (int) substr($last, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
