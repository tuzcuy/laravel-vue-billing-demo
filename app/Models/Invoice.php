<?php

namespace App\Models;

use App\Domain\Billing\BillingException;
use App\Domain\Billing\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function totalCents(): int
    {
        return (int) $this->items->sum(
            fn (InvoiceItem $item): int => $item->lineTotalCents()
        );
    }

    public function paidCents(): int
    {
        return (int) $this->payments->sum('amount_cents');
    }

    public function balanceCents(): int
    {
        return $this->totalCents() - $this->paidCents();
    }

    /**
     * Overdue is derived, never stored: an open invoice past its due date
     * with money still owed. Paying the balance "clears" it automatically.
     */
    public function isOverdue(): bool
    {
        return $this->status->acceptsPayments()
            && $this->due_date->endOfDay()->isPast()
            && $this->balanceCents() > 0;
    }

    public function effectiveStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status->value;
    }

    public function transitionTo(InvoiceStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new BillingException(
                "Cannot move invoice {$this->number} from {$this->status->value} to {$target->value}."
            );
        }

        $this->update(['status' => $target]);
    }

    /** Invoices that still expect money (sent or partially paid). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid]);
    }
}
