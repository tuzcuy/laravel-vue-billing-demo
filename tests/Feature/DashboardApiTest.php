<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_report_outstanding_overdue_and_collected_amounts(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');

        // Open, not overdue: balance 100.00 EUR.
        $open = Invoice::factory()->create([
            'status' => InvoiceStatus::Sent, 'issue_date' => '2026-11-01', 'due_date' => '2026-12-01',
        ]);
        $open->items()->create(['description' => 'A', 'quantity' => 1, 'unit_price_cents' => 10000]);

        // Overdue: balance 60.00 EUR after a 40.00 payment made this month.
        $overdue = Invoice::factory()->create([
            'status' => InvoiceStatus::PartiallyPaid, 'issue_date' => '2026-09-01', 'due_date' => '2026-10-01',
        ]);
        $overdue->items()->create(['description' => 'B', 'quantity' => 1, 'unit_price_cents' => 10000]);
        $overdue->payments()->create(['amount_cents' => 4000, 'method' => 'cash', 'paid_at' => '2026-11-05']);

        // Paid last month — contributes to nothing current.
        $paid = Invoice::factory()->create([
            'status' => InvoiceStatus::Paid, 'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
        ]);
        $paid->items()->create(['description' => 'C', 'quantity' => 1, 'unit_price_cents' => 5000]);
        $paid->payments()->create(['amount_cents' => 5000, 'method' => 'cash', 'paid_at' => '2026-10-20']);

        $response = $this->getJson('/api/dashboard/stats')->assertOk();

        $response
            ->assertJsonPath('outstanding_by_currency.EUR', 16000)
            ->assertJsonPath('overdue_by_currency.EUR', 6000)
            ->assertJsonPath('collected_this_month_by_currency.EUR', 4000)
            ->assertJsonPath('invoice_count', 3)
            ->assertJsonPath('status_counts.overdue', 1)
            ->assertJsonPath('status_counts.paid', 1)
            ->assertJsonPath('status_counts.sent', 1)
            ->assertJsonCount(1, 'recently_overdue');

        Carbon::setTestNow();
    }

    public function test_amounts_are_never_summed_across_currencies(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');

        foreach ([['EUR', 10000], ['USD', 7000]] as [$currency, $cents]) {
            $invoice = Invoice::factory()->create([
                'status' => InvoiceStatus::Sent,
                'currency' => $currency,
                'issue_date' => '2026-11-01',
                'due_date' => '2026-12-01',
            ]);
            $invoice->items()->create(['description' => 'X', 'quantity' => 1, 'unit_price_cents' => $cents]);
        }

        $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('outstanding_by_currency.EUR', 10000)
            ->assertJsonPath('outstanding_by_currency.USD', 7000);

        Carbon::setTestNow();
    }
}
