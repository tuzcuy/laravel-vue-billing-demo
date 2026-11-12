<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { api, errorMessage } from '../lib/api';
import ModalDialog from '../components/ModalDialog.vue';
import PaginationBar from '../components/PaginationBar.vue';

const customers = ref([]);
const meta = ref(null);
const search = ref('');
const loading = ref(true);
const listError = ref('');

const emptyForm = { id: null, name: '', email: '', company: '', country: '' };
const form = reactive({ ...emptyForm });
const formOpen = ref(false);
const formError = ref('');
const saving = ref(false);

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => load(1), 300);
});

async function load(page = 1) {
    loading.value = true;
    listError.value = '';
    try {
        const { data } = await api.get('/customers', { params: { page, q: search.value || undefined } });
        customers.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        listError.value = errorMessage(error);
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    Object.assign(form, emptyForm);
    formError.value = '';
    formOpen.value = true;
}

function openEdit(customer) {
    Object.assign(form, {
        id: customer.id,
        name: customer.name,
        email: customer.email,
        company: customer.company ?? '',
        country: customer.country,
    });
    formError.value = '';
    formOpen.value = true;
}

async function save() {
    saving.value = true;
    formError.value = '';
    const payload = {
        name: form.name,
        email: form.email,
        company: form.company || null,
        country: form.country,
    };
    try {
        if (form.id) {
            await api.put(`/customers/${form.id}`, payload);
        } else {
            await api.post('/customers', payload);
        }
        formOpen.value = false;
        await load(meta.value?.current_page ?? 1);
    } catch (error) {
        formError.value = errorMessage(error);
    } finally {
        saving.value = false;
    }
}

async function remove(customer) {
    if (!window.confirm(`Delete ${customer.name}?`)) return;
    try {
        await api.delete(`/customers/${customer.id}`);
        await load(meta.value?.current_page ?? 1);
    } catch (error) {
        window.alert(errorMessage(error));
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">Customers</h1>
            <div class="flex gap-3">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search name, email, company…"
                    class="w-64 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm"
                />
                <button
                    class="rounded-md bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                    @click="openCreate"
                >
                    New customer
                </button>
            </div>
        </div>

        <p v-if="listError" class="mb-4 rounded-md bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ listError }}</p>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Company</th>
                        <th class="px-4 py-3">Country</th>
                        <th class="px-4 py-3 text-right">Invoices</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-400">Loading…</td>
                    </tr>
                    <tr v-else-if="!customers.length">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-400">No customers found.</td>
                    </tr>
                    <tr
                        v-for="customer in customers"
                        v-else
                        :key="customer.id"
                        class="border-t border-slate-100 hover:bg-slate-50"
                    >
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ customer.name }}</span>
                            <span class="block text-xs text-slate-400">{{ customer.email }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ customer.company ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ customer.country }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ customer.invoices_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <button class="text-sky-700 hover:underline" @click="openEdit(customer)">Edit</button>
                            <button class="ml-3 text-rose-600 hover:underline" @click="remove(customer)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :meta="meta" @page="load" />

        <ModalDialog v-if="formOpen" :title="form.id ? 'Edit customer' : 'New customer'" @close="formOpen = false">
            <form class="space-y-4" @submit.prevent="save">
                <p v-if="formError" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ formError }}</p>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="customer-name">Name</label>
                    <input id="customer-name" v-model="form.name" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="customer-email">Email</label>
                    <input id="customer-email" v-model="form.email" type="email" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium" for="customer-company">Company</label>
                        <input id="customer-company" v-model="form.company" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium" for="customer-country">Country (ISO-2)</label>
                        <input id="customer-country" v-model="form.country" maxlength="2" placeholder="DE" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase" />
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" @click="formOpen = false">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </form>
        </ModalDialog>
    </div>
</template>
