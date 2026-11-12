<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_draft_can_be_sent(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);

        $this->postJson("/api/invoices/{$invoice->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');
    }

    public function test_sending_twice_returns_422(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);

        $this->postJson("/api/invoices/{$invoice->id}/send")->assertUnprocessable();
    }

    public function test_open_invoices_can_be_voided(): void
    {
        foreach ([InvoiceStatus::Draft, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid] as $status) {
            $invoice = Invoice::factory()->create(['status' => $status]);

            $this->postJson("/api/invoices/{$invoice->id}/void")
                ->assertOk()
                ->assertJsonPath('data.status', 'void');
        }
    }

    public function test_paid_invoices_cannot_be_voided(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Paid]);

        $this->postJson("/api/invoices/{$invoice->id}/void")->assertUnprocessable();
    }
}
