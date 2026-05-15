<script setup>
import { emptyStateVariants } from '../../design/variants';

defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div :class="emptyStateVariants.surface">
        <div class="grid gap-2">
            <p v-if="title" class="text-sm font-semibold text-zinc-300">
                {{ title }}
            </p>
            <p v-if="description">
                {{ description }}
            </p>
        </div>
        <slot />
    </div>
</template>
