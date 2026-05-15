<script setup>
import { computed } from 'vue';
import LoadingSpinner from './LoadingSpinner.vue';
import { buttonSizes, buttonVariants } from '../../design/variants';

const props = defineProps({
    variant: {
        type: String,
        default: 'secondary',
    },
    size: {
        type: String,
        default: 'md',
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    tooltip: {
        type: String,
        default: '',
    },
    iconLeft: {
        type: [Object, Function, String, null],
        default: null,
    },
    iconRight: {
        type: [Object, Function, String, null],
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const buttonClass = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-md font-semibold transition disabled:cursor-not-allowed disabled:border-zinc-800 disabled:bg-zinc-900/40 disabled:text-zinc-600',
    buttonSizes[props.size] ?? buttonSizes.md,
    buttonVariants[props.variant] ?? buttonVariants.secondary,
]);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :title="(disabled || loading) && tooltip ? tooltip : null"
        :aria-busy="loading ? 'true' : undefined"
        :class="buttonClass"
    >
        <LoadingSpinner
            v-if="loading"
            class="h-4 w-4"
        />
        <component
            :is="iconLeft"
            v-else-if="iconLeft"
            class="h-4 w-4 shrink-0"
            aria-hidden="true"
        />
        <slot />
        <component
            :is="iconRight"
            v-if="iconRight && !loading"
            class="h-4 w-4 shrink-0"
            aria-hidden="true"
        />
    </button>
</template>
