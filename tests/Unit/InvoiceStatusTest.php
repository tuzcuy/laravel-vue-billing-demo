<?php

namespace Tests\Unit;

use App\Domain\Billing\InvoiceStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvoiceStatusTest extends TestCase
{
    public static function allowedTransitions(): array
    {
        return [
            'draft can be sent' => [InvoiceStatus::Draft, InvoiceStatus::Sent],
            'draft can be voided' => [InvoiceStatus::Draft, InvoiceStatus::Void],
            'sent can be partially paid' => [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid],
            'sent can be paid in full' => [InvoiceStatus::Sent, InvoiceStatus::Paid],
            'sent can be voided' => [InvoiceStatus::Sent, InvoiceStatus::Void],
            'partially paid can settle' => [InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid],
            'partially paid can be voided' => [InvoiceStatus::PartiallyPaid, InvoiceStatus::Void],
        ];
    }

    public static function forbiddenTransitions(): array
    {
        return [
            'draft cannot jump to paid' => [InvoiceStatus::Draft, InvoiceStatus::Paid],
            'draft cannot jump to partially paid' => [InvoiceStatus::Draft, InvoiceStatus::PartiallyPaid],
            'sent cannot go back to draft' => [InvoiceStatus::Sent, InvoiceStatus::Draft],
            'paid is terminal (void)' => [InvoiceStatus::Paid, InvoiceStatus::Void],
            'paid is terminal (sent)' => [InvoiceStatus::Paid, InvoiceStatus::Sent],
            'void is terminal (sent)' => [InvoiceStatus::Void, InvoiceStatus::Sent],
            'void is terminal (paid)' => [InvoiceStatus::Void, InvoiceStatus::Paid],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions(InvoiceStatus $from, InvoiceStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_forbidden_transitions(InvoiceStatus $from, InvoiceStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    public function test_terminal_states(): void
    {
        $this->assertTrue(InvoiceStatus::Paid->isTerminal());
        $this->assertTrue(InvoiceStatus::Void->isTerminal());
        $this->assertFalse(InvoiceStatus::Draft->isTerminal());
        $this->assertFalse(InvoiceStatus::Sent->isTerminal());
    }

    public function test_only_open_states_accept_payments(): void
    {
        $this->assertTrue(InvoiceStatus::Sent->acceptsPayments());
        $this->assertTrue(InvoiceStatus::PartiallyPaid->acceptsPayments());
        $this->assertFalse(InvoiceStatus::Draft->acceptsPayments());
        $this->assertFalse(InvoiceStatus::Paid->acceptsPayments());
        $this->assertFalse(InvoiceStatus::Void->acceptsPayments());
    }
}
