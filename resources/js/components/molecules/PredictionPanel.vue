<script setup>
import UiCard from '../atoms/UiCard.vue';
import EmptyState from '../atoms/EmptyState.vue';
import SectionHeader from './SectionHeader.vue';

defineProps({
    title: {
        type: String,
        default: 'Predictions',
    },
    predictions: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <UiCard>
        <div class="grid gap-4 p-4">
            <SectionHeader
                :title="title"
                :badge="predictions.length ? 'Live' : ''"
            />

            <div
                v-if="predictions.length"
                class="grid gap-4"
            >
                <div
                    v-for="(prediction, index) in predictions"
                    :key="prediction.team_id"
                    class="grid gap-2"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="w-5 text-xs font-semibold text-zinc-500">
                                {{ index + 1 }}
                            </span>

                            <span class="text-sm font-medium text-zinc-100">
                                {{ prediction.team }}
                            </span>
                        </div>

                        <span class="text-sm font-semibold text-emerald-300">
                            {{ prediction.probability }}%
                        </span>
                    </div>

                    <div class="h-2 overflow-hidden rounded-full bg-zinc-800">
                        <div
                            class="h-full rounded-full bg-emerald-400 transition-all duration-500"
                            :style="{ width: `${prediction.probability}%` }"
                        />
                    </div>
                </div>
            </div>

            <EmptyState
                v-else
                description="Predictions will become available later in the tournament."
            />
        </div>
    </UiCard>
</template>
