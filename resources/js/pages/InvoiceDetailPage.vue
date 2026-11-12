<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, errorMessage } from '../lib/api';
import { money, toCents } from '../lib/money';
import ModalDialog from '../components/ModalDialog.vue';
import StatusBadge from '../components/StatusBadge.vue';

const props = defineProps({ id: { type: String, required: true } });
const router = useRouter();

const invoice = ref(null);
const loading = ref(true);
const actionError = ref('');

const paymentOpen = ref(false);
const paymentError = ref('');
const paying = ref(false);
const payment = reactive({ amount: '', method: 'bank_transfer', paid_at: new Date().toISOString().slice(0, 10), reference: '' });

const canSend = computed(() => invoice.value?.status === 'draft');
const canPay = computed(() => ['sent', 'partially_paid'].includes(invoice.value?.status));
const canVoid = computed(() => !['paid', 'void'].includes(invoice.value?.status));
const canDelete = computed(() => invoice.value?.status === 'draft');

async function load() {
    loading.value = true;
    try {
        invoice.value = (await api.get(`/invoices/${props.id}`)).data.data;
    } catch {
        invoice.value = null;
    } finally {
        loading.value = false;
    }
}

async function act(path) {
    actionError.value = '';
    try {
        invoice.value = (await api.post(`/invoices/${props.id}/${path}`)).data.data;
    } catch (error) {
        actionError.value = errorMessage(error);
    }
}

async function destroy() {
    if (!window.confirm(`Delete draft ${invoice.value.number}?`)) return;
    actionError.value = '';
    try {
        await api.delete(`/invoices/${props.id}`);
        router.push('/invoices');
    } catch (error) {
        actionError.value = errorMessage(error);
    }
}

function openPayment() {
    payment.amount = (invoice.value.balance_cents / 100).toFixed(2);
    payment.reference = '';
    paymentError.value = '';
    paymentOpen.value = true;
}

async function submitPayment() {
    const cents = toCents(payment.amount);
    if (!Number.isFinite(cents) || cents < 1) {
        paymentError.value = 'Enter a valid amount.';
        return;
    }
    paying.value = true;
    paymentError.value = '';
    try {
        const { data } = await api.post(`/invoices/${props.id}/payments`, {
            amount_cents: cents,
            method: payment.method,
            paid_at: payment.paid_at,
            reference: payment.reference || null,
        });
        invoice.value = data.data;
        paymentOpen.value = false;
    } catch (error) {
        paymentError.value = errorMessage(error);
    } finally {
        paying.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <p v-if="loading" class="text-slate-500">Loading…</p>
        <p v-else-if="!invoice" class="text-slate-500">
            Invoice not found.
            <RouterLink to="/invoices" class="text-sky-700 hover:underline">Back to invoices</RouterLink>
        </p>

        <template v-else>
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <RouterLink to="/invoices" class="text-sm text-slate-400 hover:text-slate-600">← Invoices</RouterLink>
                    <h1 class="mt-1 flex items-center gap-3 text-2xl font-semibold tracking-tight">
                        {{ invoice.number }}
                        <StatusBadge :status="invoice.effective_status" />
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ invoice.customer?.name }}
                        <span v-if="invoice.customer?.company" class="text-slate-400"> · {{ invoice.customer.company }}</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        v-if="canSend"
                        class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700"
                        @click="act('send')"
                    >
                        Send
                    </button>
                    <button
                        v-if="canPay"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                        @click="openPayment"
                    >
                        Record payment
                    </button>
                    <button
                        v-if="canVoid"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        @click="act('void')"
                    >
                        Void
                    </button>
                    <button
                        v-if="canDelete"
                        class="rounded-md border border-rose-200 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50"
                        @click="destroy"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <p v-if="actionError" class="mb-4 rounded-md bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ actionError }}</p>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">Items</h2>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-2">Description</th>
                                    <th class="px-5 py-2 text-right">Qty</th>
                                    <th class="px-5 py-2 text-right">Unit price</th>
                                    <th class="px-5 py-2 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in invoice.items" :key="item.id" class="border-t border-slate-100">
                                    <td class="px-5 py-2.5">{{ item.description }}</td>
                                    <td class="px-5 py-2.5 text-right tabular-nums">{{ item.quantity }}</td>
                                    <td class="px-5 py-2.5 text-right tabular-nums">{{ money(item.unit_price_cents, invoice.currency) }}</td>
                                    <td class="px-5 py-2.5 text-right tabular-nums">{{ money(item.line_total_cents, invoice.currency) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">Payments</h2>
                        <p v-if="!invoice.payments?.length" class="px-5 py-4 text-sm text-slate-400">No payments recorded.</p>
                        <table v-else class="w-full text-sm">
                            <tbody>
                                <tr v-for="p in invoice.payments" :key="p.id" class="border-t border-slate-100 first:border-0">
                                    <td class="px-5 py-2.5 text-slate-600">{{ p.paid_at }}</td>
                                    <td class="px-5 py-2.5 text-slate-500">{{ p.method.replace('_', ' ') }}</td>
                                    <td class="px-5 py-2.5 text-xs text-slate-400">{{ p.reference ?? '—' }}</td>
                                    <td class="px-5 py-2.5 text-right font-medium tabular-nums text-emerald-700">
                                        {{ money(p.amount_cents, invoice.currency) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 text-sm font-semibold text-slate-700">Summary</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Issued</dt><dd>{{ invoice.issue_date }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Due</dt><dd>{{ invoice.due_date }}</dd></div>
                            <div class="flex justify-between border-t border-slate-100 pt-2">
                                <dt class="text-slate-500">Total</dt>
                                <dd class="font-medium tabular-nums">{{ money(invoice.total_cents, invoice.currency) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Paid</dt>
                                <dd class="tabular-nums text-emerald-700">{{ money(invoice.paid_cents, invoice.currency) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-slate-100 pt-2 text-base">
                                <dt class="font-medium">Balance</dt>
                                <dd class="font-semibold tabular-nums" :class="invoice.balance_cents > 0 ? 'text-rose-600' : 'text-emerald-600'">
                                    {{ money(invoice.balance_cents, invoice.currency) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section v-if="invoice.notes" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-2 text-sm font-semibold text-slate-700">Notes</h2>
                        <p class="text-sm text-slate-600">{{ invoice.notes }}</p>
                    </section>
                </aside>
            </div>

            <ModalDialog v-if="paymentOpen" title="Record payment" @close="paymentOpen = false">
                <form class="space-y-4" @submit.prevent="submitPayment">
                    <p v-if="paymentError" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ paymentError }}</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="pay-amount">Amount ({{ invoice.currency }})</label>
                            <input id="pay-amount" v-model="payment.amount" inputmode="decimal" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                            <p class="mt-1 text-xs text-slate-400">Balance: {{ money(invoice.balance_cents, invoice.currency) }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="pay-date">Paid at</label>
                            <input id="pay-date" v-model="payment.paid_at" type="date" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="pay-method">Method</label>
                            <select id="pay-method" v-model="payment.method" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="credit_card">Credit card</option>
                                <option value="cash">Cash</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="pay-ref">Reference</label>
                            <input id="pay-ref" v-model="payment.reference" placeholder="optional" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" @click="paymentOpen = false">Cancel</button>
                        <button type="submit" :disabled="paying" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            {{ paying ? 'Saving…' : 'Record payment' }}
                        </button>
                    </div>
                </form>
            </ModalDialog>
        </template>
    </div>
</template>
