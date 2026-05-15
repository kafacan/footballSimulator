<script setup>
import UiBadge from '../atoms/UiBadge.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    eyebrow: {
        type: String,
        default: '',
    },
    badge: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-400">
                {{ eyebrow }}
            </p>
            <h2 class="text-base font-semibold text-white">
                {{ title }}
            </h2>
            <p v-if="subtitle" class="mt-1 text-sm text-zinc-400">
                {{ subtitle }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <UiBadge v-if="badge" tone="neutral">
                {{ badge }}
            </UiBadge>
            <slot name="actions" />
        </div>
    </div>
</template>
