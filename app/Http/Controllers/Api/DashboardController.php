<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

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

        $collectedThisMonth = (int) Payment::query()
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_cents');

        $recentlyOverdue = $overdue
            ->sortBy(fn (Invoice $i) => $i->due_date)
            ->take(5)
            ->values();

        return response()->json([
            'outstanding_cents' => $open->sum(fn (Invoice $i) => $i->balanceCents()),
            'overdue_cents' => $overdue->sum(fn (Invoice $i) => $i->balanceCents()),
            'collected_this_month_cents' => $collectedThisMonth,
            'invoice_count' => $invoices->count(),
            'status_counts' => $statusCounts,
            'recently_overdue' => InvoiceResource::collection($recentlyOverdue),
        ]);
    }
}
