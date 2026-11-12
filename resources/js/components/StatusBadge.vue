<script setup>
defineProps({ status: { type: String, required: true } });

const styles = {
    draft: 'bg-slate-100 text-slate-600 ring-slate-200',
    sent: 'bg-sky-50 text-sky-700 ring-sky-200',
    partially_paid: 'bg-amber-50 text-amber-700 ring-amber-200',
    paid: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    overdue: 'bg-rose-50 text-rose-700 ring-rose-200',
    void: 'bg-slate-50 text-slate-400 ring-slate-200 line-through',
};

const labels = {
    draft: 'Draft',
    sent: 'Sent',
    partially_paid: 'Partially paid',
    paid: 'Paid',
    overdue: 'Overdue',
    void: 'Void',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="styles[status] ?? styles.draft"
    >
        {{ labels[status] ?? status }}
    </span>
</template>
