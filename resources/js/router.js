import { createRouter, createWebHistory } from 'vue-router';

import DashboardPage from './pages/DashboardPage.vue';
import CustomersPage from './pages/CustomersPage.vue';
import InvoicesPage from './pages/InvoicesPage.vue';
import InvoiceDetailPage from './pages/InvoiceDetailPage.vue';
import InvoiceCreatePage from './pages/InvoiceCreatePage.vue';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'dashboard', component: DashboardPage },
        { path: '/customers', name: 'customers', component: CustomersPage },
        { path: '/invoices', name: 'invoices', component: InvoicesPage },
        { path: '/invoices/new', name: 'invoices.new', component: InvoiceCreatePage },
        { path: '/invoices/:id(\\d+)', name: 'invoices.show', component: InvoiceDetailPage, props: true },
    ],
});
