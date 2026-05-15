<script setup>
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import IconButton from '../atoms/IconButton.vue';

defineProps({
    label: {
        type: String,
        required: true,
    },
    canPrevious: {
        type: Boolean,
        default: false,
    },
    canNext: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['previous', 'next']);
</script>

<template>
    <div class="flex items-center gap-2">
        <IconButton
            :icon="ChevronLeft"
            aria-label="Previous"
            :disabled="disabled || !canPrevious"
            tooltip="No previous item"
            @click="$emit('previous')"
        />

        <div class="min-w-24 rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-center text-sm font-semibold text-white">
            {{ label }}
        </div>

        <IconButton
            :icon="ChevronRight"
            aria-label="Next"
            :disabled="disabled || !canNext"
            tooltip="No next item"
            @click="$emit('next')"
        />
    </div>
</template>
