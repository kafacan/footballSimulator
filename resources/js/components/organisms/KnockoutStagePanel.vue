<script setup>
import PredictionPanel from '../molecules/PredictionPanel.vue';
import TournamentBracket from './TournamentBracket.vue';

defineProps({
    summaries: {
        type: Object,
        default: () => ({}),
    },
    currentStage: {
        type: String,
        default: 'GROUP_STAGE',
    },
    selectedStage: {
        type: String,
        default: '',
    },
    predictions: {
        type: Array,
        default: () => [],
    },
    champion: {
        type: Object,
        default: null,
    },
    selectedLeg: {
        type: Number,
        default: 1,
    },
    matches: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['play-match', 'save-result']);
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
        <TournamentBracket
            :summaries="summaries"
            :matches="matches"
            :current-stage="currentStage"
            :selected-stage="selectedStage"
            :selected-leg="selectedLeg"
            :disabled="disabled"
            @play-match="(matchId) => emit('play-match', matchId)"
            @save-result="(matchId, scores) => emit('save-result', matchId, scores)"
        />

        <div class="grid content-start gap-6">
            <section
                v-if="champion"
                class="rounded-2xl border border-emerald-900/70 bg-emerald-950/30 p-5"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-400">
                    Champion
                </p>
                <h2 class="mt-2 text-3xl font-bold text-white">
                    {{ champion.name }}
                </h2>
            </section>

            <PredictionPanel
                title="Champion Predictions"
                :predictions="predictions"
            />
        </div>
    </div>
</template>
