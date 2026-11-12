<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'issue_date' => '2026-11-01',
            'due_date' => '2026-12-01',
            'notes' => null,
            'items' => [
                ['description' => 'Consulting', 'quantity' => 2, 'unit_price_cents' => 50000],
                ['description' => 'Hosting', 'quantity' => 1, 'unit_price_cents' => 2500],
            ],
        ], $overrides);
    }

    public function test_an_invoice_is_created_as_draft_with_computed_totals_and_number(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/invoices', $this->validPayload($customer));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', 'INV-2026-0001')
            ->assertJsonPath('data.total_cents', 102500)
            ->assertJsonPath('data.balance_cents', 102500)
            ->assertJsonCount(2, 'data.items');

        Carbon::setTestNow();
    }

    public function test_invoice_numbers_increment_and_survive_deletions(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');
        $customer = Customer::factory()->create();

        $first = $this->postJson('/api/invoices', $this->validPayload($customer))->json('data');
        $second = $this->postJson('/api/invoices', $this->validPayload($customer))->json('data');

        // Deleting an earlier draft must not cause the next number to collide.
        $this->deleteJson("/api/invoices/{$first['id']}")->assertNoContent();
        $third = $this->postJson('/api/invoices', $this->validPayload($customer))->json('data');

        $this->assertSame('INV-2026-0002', $second['number']);
        $this->assertSame('INV-2026-0003', $third['number']);

        Carbon::setTestNow();
    }

    public function test_creation_requires_at_least_one_item(): void
    {
        $customer = Customer::factory()->create();

        $this->postJson('/api/invoices', $this->validPayload($customer, ['items' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_due_date_cannot_precede_issue_date(): void
    {
        $customer = Customer::factory()->create();

        $this->postJson('/api/invoices', $this->validPayload($customer, [
            'issue_date' => '2026-11-10',
            'due_date' => '2026-11-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['due_date']);
    }

    public function test_show_includes_customer_items_and_payments(): void
    {
        $invoice = Invoice::factory()->create();
        $invoice->items()->create(['description' => 'A', 'quantity' => 1, 'unit_price_cents' => 100]);

        $this->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['customer', 'items', 'payments', 'total_cents', 'effective_status']]);
    }

    public function test_a_draft_can_be_updated_and_items_are_replaced(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->postJson('/api/invoices', $this->validPayload($customer))->json('data');

        $this->putJson("/api/invoices/{$invoice['id']}", $this->validPayload($customer, [
            'items' => [['description' => 'Single line', 'quantity' => 1, 'unit_price_cents' => 777]],
        ]))->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.total_cents', 777);
    }

    public function test_non_draft_invoices_cannot_be_updated_or_deleted(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Sent]);
        $invoice->items()->create(['description' => 'A', 'quantity' => 1, 'unit_price_cents' => 100]);

        $this->putJson("/api/invoices/{$invoice->id}", $this->validPayload($customer))
            ->assertUnprocessable();

        $this->deleteJson("/api/invoices/{$invoice->id}")->assertUnprocessable();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_overdue_filter_returns_only_open_past_due_invoices(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');

        $overdue = Invoice::factory()->create([
            'status' => InvoiceStatus::Sent, 'issue_date' => '2026-09-01', 'due_date' => '2026-10-01',
        ]);
        $overdue->items()->create(['description' => 'A', 'quantity' => 1, 'unit_price_cents' => 100]);

        // Open but not yet due.
        Invoice::factory()->create([
            'status' => InvoiceStatus::Sent, 'issue_date' => '2026-11-01', 'due_date' => '2026-12-01',
        ]);
        // Past due but already paid — must not appear.
        Invoice::factory()->create([
            'status' => InvoiceStatus::Paid, 'issue_date' => '2026-09-01', 'due_date' => '2026-10-01',
        ]);

        $this->getJson('/api/invoices?status=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $overdue->id)
            ->assertJsonPath('data.0.effective_status', 'overdue');

        Carbon::setTestNow();
    }

    public function test_invoices_can_be_searched_by_number_and_customer_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'Zebra GmbH']);
        Invoice::factory()->for($customer)->create(['number' => 'INV-2026-0042']);
        Invoice::factory()->create(['number' => 'INV-2026-0001']);

        $this->getJson('/api/invoices?q=0042')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/invoices?q=zebra')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_missing_invoice_returns_404(): void
    {
        $this->getJson('/api/invoices/999')->assertNotFound();
    }
}
