<?php

namespace App\Domain\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Applies a payment to an invoice and moves its status forward.
 *
 * All money amounts are integer cents; overpayments are rejected rather
 * than silently creating credit (out of scope for this demo).
 */
class PaymentRecorder
{
    public function record(
        Invoice $invoice,
        int $amountCents,
        string $method,
        string $paidAt,
        ?string $reference = null,
    ): Payment {
        if (! $invoice->status->acceptsPayments()) {
            throw new BillingException(
                "A {$invoice->status->value} invoice cannot accept payments."
            );
        }

        if ($amountCents < 1) {
            throw new BillingException('Payment amount must be positive.');
        }

        $balance = $invoice->balanceCents();

        if ($amountCents > $balance) {
            throw new BillingException(
                "Payment ({$amountCents} cents) exceeds the remaining balance ({$balance} cents)."
            );
        }

        return DB::transaction(function () use ($invoice, $amountCents, $method, $paidAt, $reference): Payment {
            $payment = $invoice->payments()->create([
                'amount_cents' => $amountCents,
                'method' => $method,
                'paid_at' => $paidAt,
                'reference' => $reference,
            ]);

            // Recompute the balance with the new payment included.
            $invoice->unsetRelation('payments');

            $next = $invoice->balanceCents() === 0
                ? InvoiceStatus::Paid
                : InvoiceStatus::PartiallyPaid;

            if ($invoice->status !== $next) {
                $invoice->transitionTo($next);
            }

            return $payment;
        });
    }
}
