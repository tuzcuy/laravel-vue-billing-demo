<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceMoneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_is_the_sum_of_line_totals(): void
    {
        $invoice = Invoice::factory()->create();
        $invoice->items()->create(['description' => 'A', 'quantity' => 3, 'unit_price_cents' => 1250]);
        $invoice->items()->create(['description' => 'B', 'quantity' => 1, 'unit_price_cents' => 99]);

        $this->assertSame(3 * 1250 + 99, $invoice->load('items')->totalCents());
    }

    public function test_balance_subtracts_payments_from_total(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::PartiallyPaid]);
        $invoice->items()->create(['description' => 'A', 'quantity' => 1, 'unit_price_cents' => 10000]);
        $invoice->payments()->create([
            'amount_cents' => 2500, 'method' => 'cash', 'paid_at' => now()->toDateString(),
        ]);

        $invoice->load('items', 'payments');

        $this->assertSame(10000, $invoice->totalCents());
        $this->assertSame(2500, $invoice->paidCents());
        $this->assertSame(7500, $invoice->balanceCents());
    }

    public function test_open_invoice_past_due_with_balance_is_overdue(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');

        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-09-01',
            'due_date' => '2026-10-01',
        ]);
        $invoice->items()->create(['description' => 'A', 'quantity' => 1, 'unit_price_cents' => 5000]);

        $this->assertTrue($invoice->load('items', 'payments')->isOverdue());
        $this->assertSame('overdue', $invoice->effectiveStatus());

        Carbon::setTestNow();
    }

    public function test_invoice_due_today_is_not_overdue(): void
    {
        Carbon::setTestNow('2026-11-12 23:00:00');

        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-10-13',
            'due_date' => '2026-11-12',
        ]);
        $invoice->items()->create(['description' => 'A', 'quantity' => 1, 'unit_price_cents' => 5000]);

        $this->assertFalse($invoice->load('items', 'payments')->isOverdue());
        $this->assertSame('sent', $invoice->effectiveStatus());

        Carbon::setTestNow();
    }

    public function test_draft_and_paid_invoices_are_never_overdue(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');

        foreach ([InvoiceStatus::Draft, InvoiceStatus::Paid, InvoiceStatus::Void] as $status) {
            $invoice = Invoice::factory()->create([
                'status' => $status,
                'issue_date' => '2026-01-01',
                'due_date' => '2026-02-01',
            ]);
            $invoice->items()->create(['description' => 'A', 'quantity' => 1, 'unit_price_cents' => 5000]);

            $this->assertFalse(
                $invoice->load('items', 'payments')->isOverdue(),
                "{$status->value} must never be overdue",
            );
        }

        Carbon::setTestNow();
    }
}
