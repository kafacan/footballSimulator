<script setup>
import { CheckCircle2, X } from 'lucide-vue-next';
import LoadingSpinner from '../atoms/LoadingSpinner.vue';
import IconButton from '../atoms/IconButton.vue';
import UiButton from '../atoms/UiButton.vue';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    complete: {
        type: Boolean,
        default: false,
    },
    matches: {
        type: Array,
        default: () => [],
    },
    revealedIds: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['close']);

function hasScore(match) {
    return match.home_score !== null && match.away_score !== null;
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/85 px-4"
        role="dialog"
        aria-modal="true"
    >
        <section class="w-full max-w-2xl rounded-lg border border-zinc-800 bg-zinc-950 shadow-xl">
            <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Simulation</h2>
                    <p class="mt-1 text-sm text-zinc-400">{{ complete ? 'Complete' : 'Playing matches' }}</p>
                </div>
                <IconButton
                    :icon="X"
                    aria-label="Close simulation"
                    :disabled="!complete"
                    tooltip="Simulation still running"
                    @click="$emit('close')"
                />
            </div>
            <div class="grid max-h-[60vh] gap-3 overflow-y-auto p-5">
                <article
                    v-for="match in matches"
                    :key="match.id"
                    class="grid gap-3 rounded-md border border-zinc-800 bg-zinc-900/60 p-4 sm:grid-cols-[1fr_auto]"
                >
                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-sm">
                        <span class="truncate font-semibold text-white">{{ match.home_team?.name }}</span>
                        <span class="rounded-md bg-zinc-950 px-3 py-2 font-bold text-white">
                            <template v-if="revealedIds.includes(match.id) && hasScore(match)">
                                {{ match.home_score }} - {{ match.away_score }}
                            </template>
                            <template v-else>vs</template>
                        </span>
                        <span class="truncate text-right font-semibold text-white">{{ match.away_team?.name }}</span>
                    </div>
                    <div class="flex items-center justify-end text-sm text-zinc-400">
                        <CheckCircle2 v-if="revealedIds.includes(match.id)" class="h-4 w-4 text-emerald-400" aria-hidden="true" />
                        <LoadingSpinner v-else />
                    </div>
                </article>
            </div>
            <div class="flex justify-end border-t border-zinc-800 px-5 py-4">
                <UiButton variant="primary" :disabled="!complete" tooltip="Simulation still running" @click="$emit('close')">
                    Continue
                </UiButton>
            </div>
        </section>
    </div>
</template>
