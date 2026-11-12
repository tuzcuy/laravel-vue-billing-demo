<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../lib/api';
import { money, moneyMap } from '../lib/money';
import StatCard from '../components/StatCard.vue';
import StatusBadge from '../components/StatusBadge.vue';

const stats = ref(null);
const loading = ref(true);

const STATUS_ORDER = ['draft', 'sent', 'partially_paid', 'overdue', 'paid', 'void'];
const BAR_COLORS = {
    draft: 'bg-slate-300',
    sent: 'bg-sky-400',
    partially_paid: 'bg-amber-400',
    overdue: 'bg-rose-400',
    paid: 'bg-emerald-400',
    void: 'bg-slate-200',
};

const distribution = computed(() => {
    const counts = stats.value?.status_counts ?? {};
    const total = Object.values(counts).reduce((sum, n) => sum + n, 0) || 1;
    return STATUS_ORDER
        .filter((status) => counts[status])
        .map((status) => ({
            status,
            count: counts[status],
            percent: Math.round((counts[status] / total) * 100),
        }));
});

onMounted(async () => {
    try {
        stats.value = (await api.get('/dashboard/stats')).data;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <h1 class="mb-6 text-2xl font-semibold tracking-tight">Dashboard</h1>

        <p v-if="loading" class="text-slate-500">Loading…</p>

        <template v-else-if="stats">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    label="Outstanding"
                    :value="moneyMap(stats.outstanding_by_currency)"
                    sub="Open invoices awaiting payment"
                />
                <StatCard
                    label="Overdue"
                    :value="moneyMap(stats.overdue_by_currency)"
                    sub="Past due date, still unpaid"
                    tone="danger"
                />
                <StatCard
                    label="Collected this month"
                    :value="moneyMap(stats.collected_this_month_by_currency)"
                    sub="Payments received since the 1st"
                    tone="success"
                />
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-slate-700">
                        Invoice status distribution
                        <span class="font-normal text-slate-400">({{ stats.invoice_count }} invoices)</span>
                    </h2>
                    <div class="space-y-3">
                        <div v-for="row in distribution" :key="row.status" class="flex items-center gap-3">
                            <div class="w-28 shrink-0"><StatusBadge :status="row.status" /></div>
                            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full"
                                    :class="BAR_COLORS[row.status]"
                                    :style="{ width: row.percent + '%' }"
                                />
                            </div>
                            <span class="w-10 shrink-0 text-right text-sm tabular-nums text-slate-500">
                                {{ row.count }}
                            </span>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-slate-700">Most overdue invoices</h2>
                    <p v-if="!stats.recently_overdue?.length" class="text-sm text-slate-400">
                        Nothing overdue. 🎉
                    </p>
                    <table v-else class="w-full text-sm">
                        <tbody>
                            <tr
                                v-for="invoice in stats.recently_overdue"
                                :key="invoice.id"
                                class="border-b border-slate-100 last:border-0"
                            >
                                <td class="py-2">
                                    <RouterLink
                                        :to="`/invoices/${invoice.id}`"
                                        class="font-medium text-sky-700 hover:underline"
                                    >
                                        {{ invoice.number }}
                                    </RouterLink>
                                    <span class="block text-xs text-slate-400">{{ invoice.customer?.name }}</span>
                                </td>
                                <td class="py-2 text-xs text-slate-500">due {{ invoice.due_date }}</td>
                                <td class="py-2 text-right font-medium tabular-nums text-rose-600">
                                    {{ money(invoice.balance_cents, invoice.currency) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </template>
    </div>
</template>
