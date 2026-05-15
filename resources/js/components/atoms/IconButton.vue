<script setup>
import { computed } from 'vue';
import { iconButtonSizes } from '../../design/variants';

const props = defineProps({
    icon: {
        type: [Object, Function, String],
        required: true,
    },
    variant: {
        type: String,
        default: 'ghost',
    },
    ariaLabel: {
        type: String,
        required: true,
    },
    size: {
        type: String,
        default: 'md',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    tooltip: {
        type: String,
        default: '',
    },
});

const variants = {
    ghost: 'text-zinc-400 enabled:hover:bg-zinc-800 enabled:hover:text-white',
    secondary: 'border border-zinc-800 bg-zinc-900/70 text-zinc-300 enabled:hover:border-zinc-700 enabled:hover:bg-zinc-800 enabled:hover:text-white',
    primary: 'border border-emerald-900/70 bg-emerald-500 text-zinc-950 enabled:hover:bg-emerald-400 enabled:hover:text-zinc-950',
    danger: 'border border-red-900/70 bg-red-950/40 text-red-200 enabled:hover:border-red-700 enabled:hover:bg-red-950/60 enabled:hover:text-red-100',
};

const classes = computed(() => [
    'inline-flex items-center justify-center rounded-xl transition disabled:cursor-not-allowed disabled:opacity-40',
    iconButtonSizes[props.size] ?? iconButtonSizes.md,
    variants[props.variant] ?? variants.ghost,
]);

const iconClass = computed(() => (props.size === 'lg' ? 'h-5 w-5' : 'h-4 w-4'));
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        :title="disabled && tooltip ? tooltip : null"
        :aria-label="ariaLabel"
        :class="classes"
    >
        <component :is="icon" :class="iconClass" aria-hidden="true" />
    </button>
</template>
