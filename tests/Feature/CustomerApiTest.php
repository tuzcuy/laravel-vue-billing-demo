<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_are_listed_with_pagination(): void
    {
        Customer::factory(20)->create();

        $response = $this->getJson('/api/customers');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'country', 'invoices_count']]]);
    }

    public function test_customers_can_be_searched(): void
    {
        Customer::factory()->create(['name' => 'Ada Lovelace']);
        Customer::factory()->create(['name' => 'Charles Babbage']);

        $this->getJson('/api/customers?q=lovelace')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ada Lovelace');
    }

    public function test_a_customer_can_be_created(): void
    {
        $response = $this->postJson('/api/customers', [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'company' => 'Navy Labs',
            'country' => 'us',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Grace Hopper')
            ->assertJsonPath('data.country', 'US'); // normalized to uppercase

        $this->assertDatabaseHas('customers', ['email' => 'grace@example.com', 'country' => 'US']);
    }

    public function test_creation_validates_required_fields_and_unique_email(): void
    {
        Customer::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/customers', [
            'name' => '',
            'email' => 'taken@example.com',
            'country' => 'DEU',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'country']);
    }

    public function test_a_customer_can_be_updated(): void
    {
        $customer = Customer::factory()->create();

        $this->putJson("/api/customers/{$customer->id}", [
            'name' => 'Renamed Person',
            'email' => $customer->email,
            'company' => null,
            'country' => 'NL',
        ])->assertOk()->assertJsonPath('data.name', 'Renamed Person');
    }

    public function test_missing_customer_returns_404(): void
    {
        $this->getJson('/api/customers/999')->assertNotFound();
    }

    public function test_customer_without_invoices_can_be_deleted(): void
    {
        $customer = Customer::factory()->create();

        $this->deleteJson("/api/customers/{$customer->id}")->assertNoContent();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_customer_with_invoices_cannot_be_deleted(): void
    {
        $invoice = Invoice::factory()->create();

        $this->deleteJson("/api/customers/{$invoice->customer_id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('customers', ['id' => $invoice->customer_id]);
    }
}
