<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        // Demo-scale dataset: totals are computed in PHP over eager-loaded
        // relations. See "What I'd improve next" for the SQL-side version.
        $invoices = Invoice::with(['customer', 'items', 'payments'])->get();

        $open = $invoices->filter(fn (Invoice $i) => $i->status->acceptsPayments());
        $overdue = $open->filter(fn (Invoice $i) => $i->isOverdue());

        $statusCounts = $invoices
            ->groupBy(fn (Invoice $i) => $i->effectiveStatus())
            ->map->count();

        // Amounts are never summed across currencies — each currency
        // is reported separately.
        $collectedThisMonth = Payment::query()
            ->with('invoice:id,currency')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get()
            ->groupBy(fn (Payment $p) => $p->invoice->currency)
            ->map(fn (Collection $group) => (int) $group->sum('amount_cents'));

        $recentlyOverdue = $overdue
            ->sortBy(fn (Invoice $i) => $i->due_date)
            ->take(5)
            ->values();

        return response()->json([
            'outstanding_by_currency' => $this->balanceByCurrency($open),
            'overdue_by_currency' => $this->balanceByCurrency($overdue),
            'collected_this_month_by_currency' => $collectedThisMonth,
            'invoice_count' => $invoices->count(),
            'status_counts' => $statusCounts,
            'recently_overdue' => InvoiceResource::collection($recentlyOverdue),
        ]);
    }

    private function balanceByCurrency(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy('currency')
            ->map(fn (Collection $group) => (int) $group->sum(
                fn (Invoice $i) => $i->balanceCents()
            ));
    }
}
