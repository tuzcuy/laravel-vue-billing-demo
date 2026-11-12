<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    sub: { type: String, default: '' },
    tone: { type: String, default: 'default' }, // default | danger | success
});

const tones = {
    default: 'text-slate-900',
    danger: 'text-rose-600',
    success: 'text-emerald-600',
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">{{ label }}</p>
        <p class="mt-1 truncate text-2xl font-semibold" :class="tones[tone]" :title="value">{{ value }}</p>
        <p v-if="sub" class="mt-1 text-xs text-slate-400">{{ sub }}</p>
    </div>
</template>
