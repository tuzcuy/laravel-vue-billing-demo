<?php

namespace App\Http\Controllers\Api;

use App\Domain\Billing\BillingException;
use App\Domain\Billing\PaymentRecorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

class InvoicePaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Invoice $invoice,
        PaymentRecorder $recorder,
    ): JsonResponse {
        $invoice->load(['items', 'payments']);

        try {
            $recorder->record(
                invoice: $invoice,
                amountCents: $request->integer('amount_cents'),
                method: $request->string('method')->toString(),
                paidAt: $request->string('paid_at')->toString(),
                reference: $request->input('reference'),
            );
        } catch (BillingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new InvoiceResource(
            $invoice->refresh()->load(['customer', 'items', 'payments'])
        ))->response()->setStatusCode(201);
    }
}
