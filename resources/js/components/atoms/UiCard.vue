<script setup>
import { computed } from 'vue';
import { cardVariants } from '../../design/variants';

const props = defineProps({
    variant: {
        type: String,
        default: 'surface',
    },
});

const classes = computed(() => cardVariants[props.variant] ?? cardVariants.surface);
</script>

<template>
    <section :class="classes">
        <slot />
    </section>
</template>
