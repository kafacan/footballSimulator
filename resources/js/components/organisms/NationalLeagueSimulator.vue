<script setup>
import { ArrowLeft } from 'lucide-vue-next';
import UiButton from '../atoms/UiButton.vue';
import FixturePanel from './FixturePanel.vue';
import LeagueTable from '../molecules/LeagueTable.vue';
import PredictionPanel from '../molecules/PredictionPanel.vue';
import SimulationModal from './SimulationModal.vue';
import TournamentStatusBar from './TournamentStatusBar.vue';

defineProps({
    statusLabel: {
        type: String,
        required: true,
    },
    champion: {
        type: Object,
        default: null,
    },
    standings: {
        type: Array,
        default: () => [],
    },
    predictions: {
        type: Array,
        default: () => [],
    },
    selectedWeek: {
        type: Number,
        required: true,
    },
    selectedWeekMatches: {
        type: Array,
        default: () => [],
    },
    actionInProgress: {
        type: Boolean,
        default: false,
    },
    isComplete: {
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
    canPlayMatch: {
        type: Function,
        required: true,
    },
    simulationOpen: {
        type: Boolean,
        default: false,
    },
    simulationComplete: {
        type: Boolean,
        default: false,
    },
    simulationMatches: {
        type: Array,
        default: () => [],
    },
    revealedMatchIds: {
        type: Array,
        default: () => [],
    },
});

defineEmits([
    'back',
    'previous',
    'next',
    'play-next',
    'play-all',
    'reset',
    'play-match',
    'save-result',
    'close-simulation',
]);
</script>

<template>
    <main class="min-h-screen bg-zinc-950 text-white">
        <section class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <header class="grid gap-2 border-b border-zinc-800 pb-5">
                <UiButton :icon-left="ArrowLeft" @click="$emit('back')">
                    Dashboard
                </UiButton>
                <h1 class="text-3xl font-bold tracking-normal sm:text-4xl">
                    National League Simulation
                </h1>
            </header>

            <TournamentStatusBar
                :label="statusLabel"
                play-next-label="Simulate Week"
                :champion="champion"
                :disabled="actionInProgress"
                :play-next-disabled="isComplete"
                :can-go-previous="canGoPrevious"
                :can-go-next="canGoNext"
                @previous="$emit('previous')"
                @next="$emit('next')"
                @play-next="$emit('play-next')"
                @play-all="$emit('play-all')"
                @reset="$emit('reset')"
            />

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
                <div class="grid content-start gap-6">
                    <LeagueTable title="National League Table" :standings="standings" />

                    <PredictionPanel
                        title="National League Predictions"
                        :predictions="predictions"
                    />
                </div>

                <FixturePanel
                    title="National League Fixtures"
                    :subtitle="`Week ${selectedWeek}`"
                    empty-text="No fixtures for this week."
                    :matches="selectedWeekMatches"
                    :disabled="actionInProgress"
                    :can-play-match="canPlayMatch"
                    @play-match="(matchId) => $emit('play-match', matchId)"
                    @save-result="(matchId, scores) => $emit('save-result', matchId, scores)"
                />
            </div>
        </section>

        <SimulationModal
            :open="simulationOpen"
            :complete="simulationComplete"
            :matches="simulationMatches"
            :revealed-ids="revealedMatchIds"
            @close="$emit('close-simulation')"
        />
    </main>
</template>
