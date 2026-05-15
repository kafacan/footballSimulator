<script setup>
import { FastForward, RotateCcw, SkipForward, Trophy } from 'lucide-vue-next';
import UiButton from '../atoms/UiButton.vue';
import UiCard from '../atoms/UiCard.vue';
import StepNavigator from '../molecules/StepNavigator.vue';

defineProps({
    label: {
        type: String,
        required: true,
    },
    playNextLabel: {
        type: String,
        default: 'Play Next',
    },
    champion: {
        type: Object,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    playNextDisabled: {
        type: Boolean,
        default: false,
    },
    canGoPrevious: {
        type: Boolean,
        default: false,
    },
    canGoNext: {
        type: Boolean,
        default: false,
    },
});

defineEmits([
    'play-next',
    'play-all',
    'reset',
    'previous',
    'next',
]);
</script>

<template>
    <UiCard class="p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <StepNavigator
                    :label="label"
                    :can-previous="canGoPrevious"
                    :can-next="canGoNext"
                    :disabled="disabled"
                    @previous="$emit('previous')"
                    @next="$emit('next')"
                />
                <div
                    v-if="champion"
                    class="flex items-center gap-2 text-sm font-semibold text-emerald-300"
                >
                    <Trophy class="h-4 w-4" aria-hidden="true" />
                    {{ champion.name }}
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <template v-if="playNextDisabled">
                    <UiButton
                        variant="secondary"
                        :disabled="disabled"
                        tooltip="Action in progress"
                        :icon-left="RotateCcw"
                        @click="$emit('reset')"
                    >
                        Reset All
                    </UiButton>
                </template>

                <template v-else>
                    <UiButton
                        variant="primary"
                        :disabled="disabled"
                        tooltip="Action unavailable"
                        :icon-left="SkipForward"
                        @click="$emit('play-next')"
                    >
                        {{ playNextLabel }}
                    </UiButton>

                    <UiButton
                        :disabled="disabled"
                        tooltip="Action unavailable"
                        :icon-left="FastForward"
                        @click="$emit('play-all')"
                    >
                        Simulate All
                    </UiButton>

                    <UiButton
                        :disabled="disabled"
                        tooltip="Action in progress"
                        :icon-left="RotateCcw"
                        @click="$emit('reset')"
                    >
                        Reset All
                    </UiButton>
                </template>
            </div>
        </div>
    </UiCard>
</template>
