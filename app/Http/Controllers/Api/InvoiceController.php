<?php

namespace App\Http\Controllers\Api;

use App\Domain\Billing\InvoiceNumbers;
use App\Domain\Billing\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->string('status')->toString();

        $invoices = Invoice::query()
            ->with(['customer', 'items', 'payments'])
            ->when($request->filled('customer_id'),
                fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($status === 'overdue',
                fn ($q) => $q->open()->whereDate('due_date', '<', today()))
            ->when($status !== '' && $status !== 'overdue',
                fn ($q) => $q->where('status', $status))
            ->when($request->string('q')->isNotEmpty(), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn ($q) => $q
                    ->where('number', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request, InvoiceNumbers $numbers): InvoiceResource
    {
        $data = $request->validated();

        $invoice = DB::transaction(function () use ($data, $numbers): Invoice {
            $invoice = Invoice::create([
                ...collect($data)->except('items')->all(),
                'number' => $numbers->next(),
                'status' => InvoiceStatus::Draft,
            ]);

            $invoice->items()->createMany($data['items']);

            return $invoice;
        });

        return new InvoiceResource($invoice->load(['customer', 'items', 'payments']));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(['customer', 'items', 'payments']));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return response()->json([
                'message' => 'Only draft invoices can be edited.',
            ], 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($invoice, $data): void {
            $invoice->update(collect($data)->except('items')->all());
            $invoice->items()->delete();
            $invoice->items()->createMany($data['items']);
        });

        return new InvoiceResource(
            $invoice->refresh()->load(['customer', 'items', 'payments'])
        );
    }

    public function destroy(Invoice $invoice): Response|JsonResponse
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return response()->json([
                'message' => 'Only draft invoices can be deleted. Void the invoice instead.',
            ], 422);
        }

        $invoice->delete();

        return response()->noContent();
    }
}
