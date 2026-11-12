<?php

namespace App\Http\Controllers\Api;

use App\Domain\Billing\BillingException;
use App\Domain\Billing\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

class InvoiceStatusController extends Controller
{
    public function send(Invoice $invoice): InvoiceResource|JsonResponse
    {
        return $this->transition($invoice, InvoiceStatus::Sent);
    }

    public function void(Invoice $invoice): InvoiceResource|JsonResponse
    {
        return $this->transition($invoice, InvoiceStatus::Void);
    }

    private function transition(Invoice $invoice, InvoiceStatus $target): InvoiceResource|JsonResponse
    {
        try {
            $invoice->transitionTo($target);
        } catch (BillingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new InvoiceResource(
            $invoice->refresh()->load(['customer', 'items', 'payments'])
        );
    }
}
