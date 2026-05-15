<script setup>
import { computed } from 'vue';
import { colors } from '../../design/colors';
import { radius } from '../../design/radius';
import { typography } from '../../design/tokens';

const props = defineProps({
    tone: {
        type: String,
        default: 'neutral',
    },
});

const classes = computed(() => {
    const tones = {
        neutral: 'border-zinc-800 bg-zinc-900 text-zinc-300',
        emerald: `${colors.emerald.border} ${colors.emerald.surface} ${colors.emerald.soft}`,
        red: `${colors.red.border} ${colors.red.solid}`,
        sky: 'border-sky-900/70 bg-sky-950/40 text-sky-300',
    };

    return [
        'inline-flex items-center rounded-full border px-2.5 py-1',
        radius.full,
        typography.label,
        tones[props.tone] ?? tones.neutral,
    ];
});
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
