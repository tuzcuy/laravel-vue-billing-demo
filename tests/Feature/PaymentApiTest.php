<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private function openInvoice(int $totalCents = 10000): Invoice
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);
        $invoice->items()->create([
            'description' => 'Service', 'quantity' => 1, 'unit_price_cents' => $totalCents,
        ]);

        return $invoice;
    }

    public function test_a_partial_payment_is_recorded(): void
    {
        $invoice = $this->openInvoice(10000);

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount_cents' => 4000,
            'method' => 'bank_transfer',
            'paid_at' => '2026-11-10',
            'reference' => 'TRX-1',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.paid_cents', 4000)
            ->assertJsonPath('data.balance_cents', 6000);
    }

    public function test_a_full_payment_settles_the_invoice(): void
    {
        $invoice = $this->openInvoice(10000);

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount_cents' => 10000,
            'method' => 'cash',
            'paid_at' => '2026-11-10',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.balance_cents', 0);
    }

    public function test_overpayment_returns_422_and_is_not_persisted(): void
    {
        $invoice = $this->openInvoice(10000);

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount_cents' => 10001,
            'method' => 'cash',
            'paid_at' => '2026-11-10',
        ])->assertUnprocessable();

        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_payments_on_draft_invoices_are_rejected(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
        $invoice->items()->create(['description' => 'A', 'quantity' => 1, 'unit_price_cents' => 5000]);

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount_cents' => 1000,
            'method' => 'cash',
            'paid_at' => '2026-11-10',
        ])->assertUnprocessable();
    }

    public function test_payload_is_validated(): void
    {
        $invoice = $this->openInvoice();

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount_cents' => 0,
            'method' => 'barter',
            'paid_at' => 'not-a-date',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_cents', 'method', 'paid_at']);
    }

    public function test_missing_invoice_returns_404(): void
    {
        $this->postJson('/api/invoices/999/payments', [
            'amount_cents' => 100, 'method' => 'cash', 'paid_at' => '2026-11-10',
        ])->assertNotFound();
    }
}
