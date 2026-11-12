<script setup>
defineProps({ meta: { type: Object, default: null } });
defineEmits(['page']);
</script>

<template>
    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between pt-4 text-sm">
        <p class="text-slate-500">
            {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
        </p>
        <div class="flex gap-2">
            <button
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-40"
                :disabled="meta.current_page <= 1"
                @click="$emit('page', meta.current_page - 1)"
            >
                Previous
            </button>
            <button
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-40"
                :disabled="meta.current_page >= meta.last_page"
                @click="$emit('page', meta.current_page + 1)"
            >
                Next
            </button>
        </div>
    </div>
</template>
