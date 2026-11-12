<?php

namespace Tests\Feature;

use App\Domain\Billing\BillingException;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\PaymentRecorder;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRecorderTest extends TestCase
{
    use RefreshDatabase;

    private function openInvoice(int $totalCents = 10000): Invoice
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);
        $invoice->items()->create([
            'description' => 'Service', 'quantity' => 1, 'unit_price_cents' => $totalCents,
        ]);

        return $invoice->load('items', 'payments');
    }

    private function recorder(): PaymentRecorder
    {
        return app(PaymentRecorder::class);
    }

    public function test_partial_payment_moves_invoice_to_partially_paid(): void
    {
        $invoice = $this->openInvoice(10000);

        $this->recorder()->record($invoice, 4000, 'bank_transfer', '2026-11-01');

        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);
        $this->assertSame(6000, $invoice->load('items', 'payments')->balanceCents());
    }

    public function test_full_payment_moves_invoice_to_paid(): void
    {
        $invoice = $this->openInvoice(10000);

        $this->recorder()->record($invoice, 10000, 'credit_card', '2026-11-01');

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        $this->assertSame(0, $invoice->load('items', 'payments')->balanceCents());
    }

    public function test_two_partial_payments_settle_the_invoice(): void
    {
        $invoice = $this->openInvoice(10000);
        $recorder = $this->recorder();

        $recorder->record($invoice, 4000, 'cash', '2026-11-01');
        $recorder->record($invoice->refresh()->load('items', 'payments'), 6000, 'cash', '2026-11-05');

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_overpayment_is_rejected(): void
    {
        $invoice = $this->openInvoice(10000);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessageMatches('/exceeds the remaining balance/');

        $this->recorder()->record($invoice, 10001, 'cash', '2026-11-01');
    }

    public function test_rejected_payment_is_not_persisted(): void
    {
        $invoice = $this->openInvoice(10000);

        try {
            $this->recorder()->record($invoice, 99999, 'cash', '2026-11-01');
        } catch (BillingException) {
            // expected
        }

        $this->assertSame(0, $invoice->payments()->count());
        $this->assertSame(InvoiceStatus::Sent, $invoice->refresh()->status);
    }

    public function test_draft_paid_and_void_invoices_reject_payments(): void
    {
        foreach ([InvoiceStatus::Draft, InvoiceStatus::Paid, InvoiceStatus::Void] as $status) {
            $invoice = Invoice::factory()->create(['status' => $status]);
            $invoice->items()->create([
                'description' => 'Service', 'quantity' => 1, 'unit_price_cents' => 5000,
            ]);

            try {
                $this->recorder()->record($invoice->load('items', 'payments'), 1000, 'cash', '2026-11-01');
                $this->fail("{$status->value} invoice accepted a payment");
            } catch (BillingException) {
                $this->assertSame(0, $invoice->payments()->count());
            }
        }
    }
}
