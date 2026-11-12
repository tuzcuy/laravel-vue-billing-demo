<script setup>
defineProps({ title: { type: String, required: true } });
defineEmits(['close']);
</script>

<template>
    <div class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4" @click.self="$emit('close')">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <button class="text-slate-400 hover:text-slate-600" aria-label="Close" @click="$emit('close')">✕</button>
            </div>
            <slot />
        </div>
    </div>
</template>
