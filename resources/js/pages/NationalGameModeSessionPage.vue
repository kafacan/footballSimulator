<script setup>
import { computed, onMounted, ref } from 'vue';
import { ArrowLeft } from 'lucide-vue-next';
import UiCard from '../components/atoms/UiCard.vue';
import UiButton from '../components/atoms/UiButton.vue';
import GroupStagePanel from '../components/organisms/GroupStagePanel.vue';
import SimulationModal from '../components/organisms/SimulationModal.vue';
import TournamentStatusBar from '../components/organisms/TournamentStatusBar.vue';
import {
    getGameSessionState,
    playAllSession,
    playNextSessionWeek,
    playSessionMatch,
    resetSession,
    updateSessionMatchResult,
} from '../services/leagueApi';

const props = defineProps({
    sessionId: {
        type: [Number, String],
        required: true,
    },
});

defineEmits(['navigate']);

const session = ref(null);
const groups = ref([]);
const matches = ref([]);
const standings = ref([]);
const standingsByWeek = ref({});
const predictions = ref([]);
const predictionsByWeek = ref({});
const champion = ref(null);
const selectedWeek = ref(null);
const selectedGroupId = ref(null);
const isLoading = ref(true);
const actionInProgress = ref(false);
const error = ref('');
const pendingState = ref(null);
const simulationOpen = ref(false);
const simulationComplete = ref(false);
const simulationMatches = ref([]);
const revealedMatchIds = ref([]);

const weeks = computed(() => (
    [...new Set(matches.value.map((match) => Number(match.week)))]
        .filter(Number.isFinite)
        .sort((a, b) => a - b)
));
const selectedWeekIndex = computed(() => weeks.value.indexOf(selectedWeek.value));
const canGoPreviousWeek = computed(() => selectedWeekIndex.value > 0);
const canGoNextWeek = computed(() => selectedWeekIndex.value >= 0 && selectedWeekIndex.value < weeks.value.length - 1);
const selectedGroup = computed(() => groups.value[0] ?? { id: null, name: 'League' });
const selectedGroupKey = computed(() => (selectedGroupId.value === null ? null : Number(selectedGroupId.value)));
const selectedWeekMatches = computed(() => matches.value.filter((match) => (
    Number(match.week) === Number(selectedWeek.value)
    && Number(match.group_id) === selectedGroupKey.value
)));
const remainingMatches = computed(() => matches.value.filter((match) => !isPlayed(match)));
const activeWeekMatches = computed(() => {
    const week = Math.min(...remainingMatches.value.map((match) => Number(match.week)));

    return Number.isFinite(week)
        ? remainingMatches.value.filter((match) => Number(match.week) === week)
        : [];
});
const activeWeek = computed(() => activeWeekMatches.value[0]?.week ?? weeks.value.at(-1) ?? null);
const activeMatchIds = computed(() => activeWeekMatches.value.map((match) => match.id));
const selectedWeekIsComplete = computed(() => {
    const weekMatches = matches.value.filter((match) => Number(match.week) === Number(selectedWeek.value));
    return weekMatches.length > 0 && weekMatches.every(isPlayed);
});
const selectedStandings = computed(() => {
    const source = selectedWeekIsComplete.value && standingsByWeek.value[selectedWeek.value]
        ? standingsByWeek.value[selectedWeek.value]
        : standings.value;

    return source.find((group) => Number(group.group_id) === selectedGroupKey.value)?.rows ?? [];
});
const selectedPredictions = computed(() => predictionsByWeek.value[selectedWeek.value]?.[0]?.rows
    ?? predictions.value[0]?.rows
    ?? []);
const isComplete = computed(() => session.value?.status === 'finished');
const statusLabel = computed(() => `${session.value?.name ?? 'National League'} · Week ${selectedWeek.value ?? '-'}`);

async function loadSession() {
    await runAction(() => getGameSessionState(props.sessionId));
}

async function playNext() {
    const plannedMatches = activeWeekMatches.value;

    if (!plannedMatches.length || isComplete.value) {
        return;
    }

    error.value = '';
    actionInProgress.value = true;
    simulationOpen.value = true;
    simulationComplete.value = false;
    simulationMatches.value = plannedMatches;
    revealedMatchIds.value = [];

    try {
        const state = await playNextSessionWeek(props.sessionId);
        const ids = plannedMatches.map((match) => match.id);

        simulationMatches.value = (state.matches ?? [])
            .filter((match) => ids.includes(match.id));
        pendingState.value = state;

        for (const match of simulationMatches.value) {
            await wait(350);
            revealedMatchIds.value = [...revealedMatchIds.value, match.id];
        }

        simulationComplete.value = true;
    } catch (exception) {
        error.value = exception.message;
        simulationOpen.value = false;
    } finally {
        actionInProgress.value = false;
        isLoading.value = false;
    }
}

async function playAll() {
    await runAction(() => playAllSession(props.sessionId));
}

async function playSingleMatch(matchId) {
    await runAction(() => playSessionMatch(props.sessionId, matchId));
}

async function saveResult(matchId, scores) {
    await runAction(() => updateSessionMatchResult(props.sessionId, matchId, scores));
}

async function runAction(action) {
    error.value = '';
    actionInProgress.value = true;

    try {
        applyState(await action());
    } catch (exception) {
        error.value = exception.message;
    } finally {
        actionInProgress.value = false;
        isLoading.value = false;
    }
}

function applyState(state) {
    session.value = state.session;
    groups.value = state.groups?.length ? state.groups : [{ id: null, name: 'League' }];
    matches.value = state.matches ?? [];
    standings.value = state.standings ?? [];
    standingsByWeek.value = state.standings_by_week ?? {};
    predictions.value = state.predictions ?? [];
    predictionsByWeek.value = state.predictions_by_week ?? {};
    champion.value = state.champion ?? null;
    selectedGroupId.value = groups.value[0]?.id ?? null;
    selectedWeek.value = session.value?.current_week ?? selectedWeek.value ?? weeks.value[0] ?? null;
}

async function resetNationalSession() {
    if (!session.value) {
        return;
    }

    simulationOpen.value = false;
    simulationComplete.value = false;
    simulationMatches.value = [];
    revealedMatchIds.value = [];
    pendingState.value = null;

    await runAction(() => resetSession(props.sessionId));
}

function closeSimulation() {
    simulationOpen.value = false;
    simulationComplete.value = false;

    if (pendingState.value) {
        applyState(pendingState.value);
        pendingState.value = null;
        selectedWeek.value = activeWeek.value;
    }
}

function goToPreviousWeek() {
    if (canGoPreviousWeek.value) {
        selectedWeek.value = weeks.value[selectedWeekIndex.value - 1];
    }
}

function goToNextWeek() {
    if (canGoNextWeek.value) {
        selectedWeek.value = weeks.value[selectedWeekIndex.value + 1];
    }
}

function canPlayMatch(match) {
    return activeMatchIds.value.includes(match.id);
}

function isPlayed(match) {
    return match.home_score !== null && match.away_score !== null;
}

function wait(ms) {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

onMounted(loadSession);
</script>

<template>
    <main class="min-h-screen bg-zinc-950 text-white">
        <section class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <header class="grid gap-2 border-b border-zinc-800 pb-5">
                <h1 class="text-3xl font-bold tracking-normal sm:text-4xl">
                    {{ session?.name ?? 'National League' }}
                </h1>
            </header>

            <TournamentStatusBar
                :label="statusLabel"
                play-next-label="Simulate Week"
                :champion="champion"
                :disabled="actionInProgress"
                :play-next-disabled="isComplete"
                :can-go-previous="canGoPreviousWeek"
                :can-go-next="canGoNextWeek"
                @previous="goToPreviousWeek"
                @next="goToNextWeek"
                @play-next="playNext"
                @play-all="playAll"
                @reset="resetNationalSession"
            />

            <div
                v-if="error"
                class="rounded-lg border border-red-900/80 bg-red-950/50 px-4 py-3 text-sm text-red-100"
                role="alert"
            >
                {{ error }}
            </div>

            <UiCard v-if="isLoading" class="p-6 text-sm text-zinc-300">
                Loading session...
            </UiCard>

            <GroupStagePanel
                v-else
                :selected-group="selectedGroup"
                :selected-week="selectedWeek"
                :standings="selectedStandings"
                :predictions="selectedPredictions"
                :matches="selectedWeekMatches"
                :disabled="actionInProgress"
                :can-play-match="canPlayMatch"
                :groups="groups"
                :selected-group-id="selectedGroupId"
                @update:selected-group-id="selectedGroupId = $event"
                @play-match="playSingleMatch"
                @save-result="saveResult"
            />
        </section>

        <SimulationModal
            :open="simulationOpen"
            :complete="simulationComplete"
            :matches="simulationMatches"
            :revealed-ids="revealedMatchIds"
            @close="closeSimulation"
        />
    </main>
</template>
