<script setup>
import { RouterLink, RouterView } from 'vue-router';

const nav = [
    { to: '/', label: 'Dashboard', exact: true },
    { to: '/invoices', label: 'Invoices' },
    { to: '/customers', label: 'Customers' },
];
</script>

<template>
    <div class="min-h-screen">
        <header class="bg-slate-900 text-white">
            <div class="mx-auto flex max-w-6xl items-center gap-8 px-4 py-3">
                <RouterLink to="/" class="flex items-center gap-2 font-semibold tracking-tight">
                    <span class="grid size-7 place-items-center rounded-lg bg-emerald-500 text-sm font-bold">L</span>
                    Ledgerline
                    <span class="rounded-full bg-slate-700 px-2 py-0.5 text-[11px] font-normal text-slate-300">demo</span>
                </RouterLink>
                <nav class="flex gap-1 text-sm">
                    <RouterLink
                        v-for="item in nav"
                        :key="item.to"
                        :to="item.to"
                        class="rounded-md px-3 py-1.5 text-slate-300 hover:bg-slate-800 hover:text-white"
                        active-class="bg-slate-800 !text-white"
                        :exact-active-class="item.exact ? 'bg-slate-800 !text-white' : undefined"
                    >
                        {{ item.label }}
                    </RouterLink>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <RouterView />
        </main>

        <footer class="mx-auto max-w-6xl px-4 pb-8 text-xs text-slate-400">
            Synthetic demo data — every name, amount and invoice is generated.
        </footer>
    </div>
</template>
