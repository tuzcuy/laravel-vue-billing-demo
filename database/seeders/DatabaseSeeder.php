<?php

namespace Database\Seeders;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds a realistic, fully synthetic billing dataset:
 * 18 customers and 60 invoices in a controlled status mix
 * (drafts, open, overdue, partially paid, paid, void), with
 * payments that are always consistent with the invoice status.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::factory(18)->create();

        $buckets = [
            // [status, how many, overdue?]
            [InvoiceStatus::Draft, 10, false],
            [InvoiceStatus::Sent, 10, false],
            [InvoiceStatus::Sent, 6, true],
            [InvoiceStatus::PartiallyPaid, 8, false],
            [InvoiceStatus::PartiallyPaid, 5, true],
            [InvoiceStatus::Paid, 16, false],
            [InvoiceStatus::Void, 5, false],
        ];

        foreach ($buckets as [$status, $count, $overdue]) {
            for ($i = 0; $i < $count; $i++) {
                $this->makeInvoice($customers->random(), $status, $overdue);
            }
        }
    }

    private function makeInvoice(Customer $customer, InvoiceStatus $status, bool $overdue): void
    {
        $issueDate = match (true) {
            // Overdue: due date (issue + 30d) already passed.
            $overdue => now()->subDays(random_int(45, 120)),
            // Open but not overdue: due date still in the future.
            $status->acceptsPayments() => now()->subDays(random_int(0, 25)),
            default => now()->subDays(random_int(0, 90)),
        };

        $invoice = Invoice::factory()
            ->for($customer)
            ->create([
                'status' => $status,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $issueDate->copy()->addDays(30)->toDateString(),
            ]);

        InvoiceItem::factory(random_int(1, 5))->create(['invoice_id' => $invoice->id]);

        $total = $invoice->load('items')->totalCents();

        match ($status) {
            InvoiceStatus::Paid => $this->addPayments($invoice, $total, $issueDate),
            InvoiceStatus::PartiallyPaid => $this->addPayments(
                $invoice,
                // Pay 20–70% of the total, at least one cent short of settling it.
                max(1, min((int) floor($total * random_int(20, 70) / 100), $total - 1)),
                $issueDate,
            ),
            default => null,
        };
    }

    private function addPayments(Invoice $invoice, int $amountCents, Carbon $issueDate): void
    {
        if ($amountCents < 1) {
            return;
        }

        // Sometimes split into two installments for realism.
        $parts = $amountCents >= 10000 && random_int(0, 1) === 1
            ? [intdiv($amountCents, 2), $amountCents - intdiv($amountCents, 2)]
            : [$amountCents];

        foreach ($parts as $index => $part) {
            $invoice->payments()->create([
                'amount_cents' => $part,
                'method' => fake()->randomElement(Payment::METHODS),
                'paid_at' => $issueDate->copy()
                    ->addDays(5 + $index * 12)
                    ->min(now())
                    ->toDateString(),
                'reference' => fake()->optional(0.6)->bothify('TRX-########'),
            ]);
        }
    }
}
