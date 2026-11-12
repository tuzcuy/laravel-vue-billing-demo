<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, errorMessage } from '../lib/api';
import { money, toCents } from '../lib/money';

const router = useRouter();

const customers = ref([]);
const saving = ref(false);
const formError = ref('');

const today = new Date().toISOString().slice(0, 10);
const in30days = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);

const form = reactive({
    customer_id: '',
    currency: 'EUR',
    issue_date: today,
    due_date: in30days,
    notes: '',
    items: [newItem()],
});

function newItem() {
    return { description: '', quantity: 1, unit_price: '' };
}

const totalCents = computed(() =>
    form.items.reduce((sum, item) => {
        const cents = toCents(item.unit_price);
        const qty = Number.parseInt(item.quantity, 10);
        if (!Number.isFinite(cents) || !Number.isInteger(qty)) return sum;
        return sum + cents * qty;
    }, 0),
);

async function loadCustomers() {
    // The demo dataset is small; fetch the first pages for the dropdown.
    const first = (await api.get('/customers', { params: { page: 1 } })).data;
    customers.value = first.data;
    for (let page = 2; page <= first.meta.last_page; page++) {
        customers.value.push(...(await api.get('/customers', { params: { page } })).data.data);
    }
}

async function save() {
    formError.value = '';
    const items = form.items.map((item) => ({
        description: item.description,
        quantity: Number.parseInt(item.quantity, 10),
        unit_price_cents: toCents(item.unit_price),
    }));

    if (items.some((item) => !Number.isFinite(item.unit_price_cents))) {
        formError.value = 'Every line needs a valid unit price.';
        return;
    }

    saving.value = true;
    try {
        const { data } = await api.post('/invoices', {
            customer_id: form.customer_id,
            currency: form.currency,
            issue_date: form.issue_date,
            due_date: form.due_date,
            notes: form.notes || null,
            items,
        });
        router.push(`/invoices/${data.data.id}`);
    } catch (error) {
        formError.value = errorMessage(error);
    } finally {
        saving.value = false;
    }
}

onMounted(loadCustomers);
</script>

<template>
    <div class="max-w-3xl">
        <RouterLink to="/invoices" class="text-sm text-slate-400 hover:text-slate-600">← Invoices</RouterLink>
        <h1 class="mb-6 mt-1 text-2xl font-semibold tracking-tight">New invoice</h1>

        <form class="space-y-6" @submit.prevent="save">
            <p v-if="formError" class="rounded-md bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ formError }}</p>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium" for="inv-customer">Customer</label>
                        <select id="inv-customer" v-model="form.customer_id" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="" disabled>Select a customer…</option>
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }}<template v-if="customer.company"> — {{ customer.company }}</template>
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium" for="inv-currency">Currency</label>
                        <select id="inv-currency" v-model="form.currency" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option>EUR</option>
                            <option>USD</option>
                            <option>GBP</option>
                            <option>TRY</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium" for="inv-issue">Issue date</label>
                        <input id="inv-issue" v-model="form.issue_date" type="date" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium" for="inv-due">Due date</label>
                        <input id="inv-due" v-model="form.due_date" type="date" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-700">Line items</h2>
                <div class="space-y-3">
                    <div v-for="(item, index) in form.items" :key="index" class="flex items-start gap-3">
                        <input
                            v-model="item.description"
                            placeholder="Description"
                            required
                            class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        <input
                            v-model="item.quantity"
                            type="number"
                            min="1"
                            required
                            class="w-20 rounded-md border border-slate-300 px-3 py-2 text-right text-sm"
                        />
                        <input
                            v-model="item.unit_price"
                            inputmode="decimal"
                            :placeholder="`Unit price (${form.currency})`"
                            required
                            class="w-40 rounded-md border border-slate-300 px-3 py-2 text-right text-sm"
                        />
                        <button
                            type="button"
                            class="mt-1 text-slate-400 hover:text-rose-600 disabled:opacity-30"
                            :disabled="form.items.length === 1"
                            aria-label="Remove line"
                            @click="form.items.splice(index, 1)"
                        >
                            ✕
                        </button>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <button type="button" class="text-sm font-medium text-sky-700 hover:underline" @click="form.items.push(newItem())">
                        + Add line
                    </button>
                    <p class="text-sm text-slate-500">
                        Total: <span class="text-base font-semibold text-slate-900 tabular-nums">{{ money(totalCents, form.currency) }}</span>
                    </p>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <label class="mb-1 block text-sm font-medium" for="inv-notes">Notes</label>
                <textarea id="inv-notes" v-model="form.notes" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Optional"></textarea>
            </section>

            <div class="flex justify-end gap-3">
                <RouterLink to="/invoices" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</RouterLink>
                <button type="submit" :disabled="saving" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                    {{ saving ? 'Creating…' : 'Create draft' }}
                </button>
            </div>
        </form>
    </div>
</template>
