<?php

namespace App\Domain\Billing;

/**
 * Stored invoice lifecycle states and the transitions allowed between them.
 *
 * "Overdue" is intentionally NOT a stored state: it is derived from
 * due_date + outstanding balance at read time (see Invoice::isOverdue()),
 * so an invoice can never be "stuck" overdue after a late payment.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Void],
            self::Sent => [self::PartiallyPaid, self::Paid, self::Void],
            self::PartiallyPaid => [self::Paid, self::Void],
            self::Paid, self::Void => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function acceptsPayments(): bool
    {
        return in_array($this, [self::Sent, self::PartiallyPaid], true);
    }
}
