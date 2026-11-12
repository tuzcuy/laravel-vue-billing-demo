<script setup>
import { onMounted, ref, watch } from 'vue';
import { api, errorMessage } from '../lib/api';
import { money } from '../lib/money';
import PaginationBar from '../components/PaginationBar.vue';
import StatusBadge from '../components/StatusBadge.vue';

const invoices = ref([]);
const meta = ref(null);
const loading = ref(true);
const listError = ref('');
const status = ref('');
const search = ref('');

const FILTERS = [
    { value: '', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'partially_paid', label: 'Partially paid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'paid', label: 'Paid' },
    { value: 'void', label: 'Void' },
];

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => load(1), 300);
});
watch(status, () => load(1));

async function load(page = 1) {
    loading.value = true;
    listError.value = '';
    try {
        const { data } = await api.get('/invoices', {
            params: {
                page,
                status: status.value || undefined,
                q: search.value || undefined,
            },
        });
        invoices.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        listError.value = errorMessage(error);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">Invoices</h1>
            <div class="flex gap-3">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search number or customer…"
                    class="w-64 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm"
                />
                <RouterLink
                    to="/invoices/new"
                    class="rounded-md bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                >
                    New invoice
                </RouterLink>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="filter in FILTERS"
                :key="filter.value"
                class="rounded-full px-3 py-1 text-sm"
                :class="status === filter.value
                    ? 'bg-slate-900 text-white'
                    : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                @click="status = filter.value"
            >
                {{ filter.label }}
            </button>
        </div>

        <p v-if="listError" class="mb-4 rounded-md bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ listError }}</p>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-400">Loading…</td>
                    </tr>
                    <tr v-else-if="!invoices.length">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-400">No invoices match this filter.</td>
                    </tr>
                    <tr
                        v-for="invoice in invoices"
                        v-else
                        :key="invoice.id"
                        class="border-t border-slate-100 hover:bg-slate-50"
                    >
                        <td class="px-4 py-3">
                            <RouterLink :to="`/invoices/${invoice.id}`" class="font-medium text-sky-700 hover:underline">
                                {{ invoice.number }}
                            </RouterLink>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ invoice.customer?.name }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="invoice.effective_status" /></td>
                        <td class="px-4 py-3 text-slate-600">{{ invoice.due_date }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ money(invoice.total_cents, invoice.currency) }}
                        </td>
                        <td
                            class="px-4 py-3 text-right font-medium tabular-nums"
                            :class="invoice.balance_cents > 0 && invoice.effective_status === 'overdue' ? 'text-rose-600' : ''"
                        >
                            {{ money(invoice.balance_cents, invoice.currency) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :meta="meta" @page="load" />
    </div>
</template>
