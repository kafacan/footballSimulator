<script setup>
import { computed, onMounted, ref } from 'vue';
import UiCard from '../components/atoms/UiCard.vue';
import GroupStagePanel from '../components/organisms/GroupStagePanel.vue';
import KnockoutStagePanel from '../components/organisms/KnockoutStagePanel.vue';
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

const STAGE_GROUP = 'GROUP_STAGE';
const STAGE_FINISHED = 'FINISHED';
const stageOrder = ['ROUND_OF_16', 'QUARTER_FINAL', 'SEMI_FINAL', 'FINAL'];

const props = defineProps({
    sessionId: {
        type: [Number, String],
        required: true,
    },
});

const teams = ref([]);
const groups = ref([]);
const matches = ref([]);
const knockoutMatches = ref({});
const knockoutSummaries = ref({});
const currentStage = ref(STAGE_GROUP);
const champion = ref(null);
const tournamentWinnerPredictions = ref([]);
const tournamentWinnerPredictionsByStage = ref({});
const standings = ref([]);
const standingsByWeek = ref({});
const predictions = ref([]);
const predictionsByWeek = ref({});

const selectedMode = ref(STAGE_GROUP); // GROUP_STAGE | KNOCKOUT
const selectedWeek = ref(null);
const selectedGroupId = ref(null);
const selectedKnockoutStage = ref('');
const selectedKnockoutLeg = ref(1);

const isLoading = ref(true);
const actionInProgress = ref(false);
const error = ref('');

const pendingState = ref(null);
const simulationOpen = ref(false);
const simulationComplete = ref(false);
const simulationMatches = ref([]);
const revealedMatchIds = ref([]);

const isCurrentGroupStage = computed(() => currentStage.value === STAGE_GROUP);
const isViewingGroupStage = computed(() => selectedMode.value === STAGE_GROUP);

const groupStageMatches = computed(() => matches.value.filter((match) => match.stage === STAGE_GROUP));
const flatKnockoutMatches = computed(() => Object.values(knockoutMatches.value).flat());

const weeks = computed(() => (
    [...new Set(groupStageMatches.value.map((match) => Number(match.week)))]
        .filter(Number.isFinite)
        .sort((a, b) => a - b)
));

const firstGroupWeek = computed(() => weeks.value[0] ?? null);
const lastGroupWeek = computed(() => weeks.value.at(-1) ?? null);

const availableGroups = computed(() => (groups.value.length ? groups.value : [{ id: null, name: 'League' }]));
const selectedGroupKey = computed(() => (selectedGroupId.value === null ? null : Number(selectedGroupId.value)));
const selectedGroup = computed(() => (
    availableGroups.value.find((group) => Number(group.id) === selectedGroupKey.value) ?? availableGroups.value[0] ?? null
));

const selectedWeekIndex = computed(() => weeks.value.indexOf(selectedWeek.value));
const canGoPreviousWeek = computed(() => selectedWeekIndex.value > 0);
const canGoNextWeek = computed(() => selectedWeekIndex.value >= 0 && selectedWeekIndex.value < weeks.value.length - 1);

const selectedWeekMatches = computed(() => groupStageMatches.value.filter((match) => (
    Number(match.week) === Number(selectedWeek.value)
    && Number(match.group_id) === selectedGroupKey.value
)));

const selectedWeekIsComplete = computed(() => (
    selectedWeekMatches.value.length > 0
    && selectedWeekMatches.value.every(isPlayed)
));

const selectedStandings = computed(() => {
    const source = selectedWeekIsComplete.value && standingsByWeek.value[selectedWeek.value]
        ? standingsByWeek.value[selectedWeek.value]
        : standings.value;

    return source.find((group) => Number(group.group_id) === selectedGroupKey.value)?.rows ?? [];
});

const selectedPredictions = computed(() => {
    const source = selectedWeekIsComplete.value
        ? predictionsByWeek.value[selectedWeek.value] ?? []
        : predictions.value;

    return source.find((group) => Number(group.group_id) === selectedGroupKey.value)?.rows ?? [];
});

const knockoutStageList = computed(() => stageOrder.filter((stage) => (
    knockoutMatches.value[stage]?.length || knockoutSummaries.value[stage]?.length
)));

const selectedKnockoutStageIndex = computed(() => knockoutStageList.value.indexOf(selectedKnockoutStage.value));

const selectedKnockoutMatches = computed(() => (
    knockoutMatches.value[selectedKnockoutStage.value]?.filter((match) => Number(match.leg ?? 1) === Number(selectedKnockoutLeg.value)) ?? []
));

const selectedTournamentWinnerPredictions = computed(() => (
    tournamentWinnerPredictionsByStage.value[selectedKnockoutStage.value]
    ?? tournamentWinnerPredictions.value
));

const activeGroupStepMatches = computed(() => {
    const unplayed = groupStageMatches.value.filter((match) => !isPlayed(match));
    const week = Math.min(...unplayed.map((match) => Number(match.week)));

    return Number.isFinite(week)
        ? unplayed.filter((match) => Number(match.week) === week)
        : [];
});

const activeKnockoutStepMatches = computed(() => {
    const stage = currentStage.value;

    if (!stageOrder.includes(stage)) {
        return [];
    }

    const source = flatKnockoutMatches.value.filter((match) => match.stage === stage);
    const unplayed = source.filter((match) => !isPlayed(match));

    if (stage === 'FINAL') {
        return unplayed;
    }

    const leg = Math.min(...unplayed.map((match) => Number(match.leg ?? 1)));

    return Number.isFinite(leg)
        ? unplayed.filter((match) => Number(match.leg ?? 1) === leg)
        : [];
});

const activeStepMatches = computed(() => (
    isCurrentGroupStage.value ? activeGroupStepMatches.value : activeKnockoutStepMatches.value
));

const activeWeek = computed(() => activeGroupStepMatches.value[0]?.week ?? lastGroupWeek.value);
const activeMatchIds = computed(() => activeStepMatches.value.map((match) => match.id));

const canGoPreviousKnockoutStep = computed(() => {
    if (isViewingGroupStage.value || !selectedKnockoutStage.value) {
        return false;
    }

    if (selectedKnockoutStage.value === 'ROUND_OF_16' && selectedKnockoutLeg.value === 1) {
        return weeks.value.length > 0;
    }

    if (selectedKnockoutStage.value !== 'FINAL' && selectedKnockoutLeg.value === 2) {
        return true;
    }

    return selectedKnockoutStageIndex.value > 0;
});

const canGoNextKnockoutStep = computed(() => {
    if (isViewingGroupStage.value || !selectedKnockoutStage.value) {
        return false;
    }

    if (selectedKnockoutStage.value !== 'FINAL' && selectedKnockoutLeg.value === 1) {
        return true;
    }

    return selectedKnockoutStageIndex.value >= 0
        && selectedKnockoutStageIndex.value < knockoutStageList.value.length - 1;
});

const canGoNextFromGroupTimeline = computed(() => (
    canGoNextWeek.value || knockoutStageList.value.length > 0
));

const topBarCanPrevious = computed(() => (
    isViewingGroupStage.value ? canGoPreviousWeek.value : canGoPreviousKnockoutStep.value
));

const topBarCanNext = computed(() => (
    isViewingGroupStage.value ? canGoNextFromGroupTimeline.value : canGoNextKnockoutStep.value
));

const hasLeagueData = computed(() => (
    teams.value.length > 0
    || groupStageMatches.value.length > 0
    || flatKnockoutMatches.value.length > 0
    || standings.value.length > 0
));

const statusLabel = computed(() => {
    if (isViewingGroupStage.value) {
        return `Group Stage · Week ${selectedWeek.value ?? '-'}`;
    }

    if (selectedKnockoutStage.value === 'FINAL') {
        return 'Final';
    }

    return `${formatStage(selectedKnockoutStage.value)} · Leg ${selectedKnockoutLeg.value}`;
});

const playNextLabel = computed(() => {
    if (currentStage.value === STAGE_FINISHED) {
        return 'Tournament Finished';
    }

    if (currentStage.value === STAGE_GROUP) {
        return 'Simulate Week';
    }

    if (currentStage.value === 'FINAL') {
        return 'Simulate Final';
    }

    return 'Simulate Leg';
});

function applyState(state, options = {}) {
    teams.value = state.teams ?? [];
    groups.value = state.groups ?? [];
    matches.value = state.matches ?? [];
    knockoutMatches.value = state.knockout_matches ?? {};
    knockoutSummaries.value = state.knockout_summaries ?? {};
    currentStage.value = state.current_stage ?? STAGE_GROUP;
    champion.value = state.champion ?? null;
    tournamentWinnerPredictions.value = state.tournament_winner_predictions ?? [];
    tournamentWinnerPredictionsByStage.value = state.tournament_winner_predictions_by_stage ?? {};
    standings.value = state.standings ?? [];
    standingsByWeek.value = state.standings_by_week ?? {};
    predictions.value = state.predictions ?? [];
    predictionsByWeek.value = state.predictions_by_week ?? {};

    syncSelectedGroup();
    syncSelectedWeek(options);
    syncSelectedKnockout(options);
    syncSelectedMode(options);
}

async function runAction(action, options = {}) {
    error.value = '';
    actionInProgress.value = true;

    try {
        applyState(await action(), options);
    } catch (exception) {
        error.value = exception.message;
    } finally {
        actionInProgress.value = false;
        isLoading.value = false;
    }
}

async function loadLeague() {
    isLoading.value = true;
    await runAction(() => getGameSessionState(props.sessionId));
}

async function playNextWithModal() {
    const plannedMatches = activeStepMatches.value;

    if (plannedMatches.length === 0 || currentStage.value === STAGE_FINISHED) {
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
        const returnedMatches = extractMatchesFromState(state);
        const ids = plannedMatches.map((match) => match.id);

        simulationMatches.value = ids
            .map((id) => returnedMatches.find((match) => match.id === id))
            .filter(Boolean);

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
    }
}

function closeSimulation() {
    simulationOpen.value = false;
    simulationComplete.value = false;

    if (pendingState.value) {
        applyState(pendingState.value, { seekActiveStep: true });
        pendingState.value = null;
    }
}

function saveResult(matchId, scores) {
    runAction(
        () => updateSessionMatchResult(props.sessionId, matchId, scores),
        { preserveView: true },
    );
}

function playSingleMatch(matchId) {
    runAction(
        () => playSessionMatch(props.sessionId, matchId),
        { seekActiveStep: true },
    );
}

function playAllWeeks() {
    runAction(() => playAllSession(props.sessionId), { seekCurrentStage: true });
}

function resetFixtures() {
    runAction(() => resetSession(props.sessionId), { seekFirstWeek: true });
}

function syncSelectedGroup() {
    if (!availableGroups.value.some((group) => Number(group.id) === selectedGroupKey.value)) {
        selectedGroupId.value = availableGroups.value[0]?.id ?? null;
    }
}

function syncSelectedWeek(options = {}) {
    if (!weeks.value.length) {
        selectedWeek.value = null;
        return;
    }

    if (options.seekFirstWeek) {
        selectedWeek.value = firstGroupWeek.value;
        return;
    }

    if (options.seekActiveStep && currentStage.value === STAGE_GROUP) {
        selectedWeek.value = activeWeek.value;
        return;
    }

    if (!weeks.value.includes(Number(selectedWeek.value))) {
        selectedWeek.value = currentStage.value === STAGE_GROUP
            ? activeWeek.value ?? firstGroupWeek.value
            : lastGroupWeek.value;
    }
}

function syncSelectedKnockout(options = {}) {
    if (!knockoutStageList.value.length) {
        selectedKnockoutStage.value = '';
        selectedKnockoutLeg.value = 1;
        return;
    }

    if (options.seekCurrentStage && stageOrder.includes(currentStage.value)) {
        selectedKnockoutStage.value = currentStage.value;
    }

    if (!knockoutStageList.value.includes(selectedKnockoutStage.value)) {
        selectedKnockoutStage.value = stageOrder.includes(currentStage.value)
            ? currentStage.value
            : knockoutStageList.value.at(-1);
    }

    if (selectedKnockoutStage.value === 'FINAL') {
        selectedKnockoutLeg.value = 1;
        return;
    }

    if (![1, 2].includes(Number(selectedKnockoutLeg.value))) {
        selectedKnockoutLeg.value = 1;
    }
}

function syncSelectedMode(options = {}) {
    if (options.preserveView) {
        return;
    }

    if (currentStage.value === STAGE_GROUP) {
        selectedMode.value = STAGE_GROUP;
        return;
    }

    if (options.seekFirstWeek) {
        selectedMode.value = STAGE_GROUP;
        return;
    }

    if (options.seekCurrentStage || options.seekActiveStep) {
        selectedMode.value = 'KNOCKOUT';
    }
}

function goToPreviousStep() {
    if (isViewingGroupStage.value) {
        goToPreviousWeek();
        return;
    }

    if (selectedKnockoutStage.value === 'ROUND_OF_16' && Number(selectedKnockoutLeg.value) === 1) {
        selectedMode.value = STAGE_GROUP;
        selectedWeek.value = lastGroupWeek.value;
        return;
    }

    goToPreviousKnockoutStep();
}

function goToNextStep() {
    if (isViewingGroupStage.value) {
        if (canGoNextWeek.value) {
            goToNextWeek();
            return;
        }

        if (knockoutStageList.value.length) {
            selectedMode.value = 'KNOCKOUT';
            selectedKnockoutStage.value = knockoutStageList.value[0];
            selectedKnockoutLeg.value = 1;
        }

        return;
    }

    goToNextKnockoutStep();
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

function goToPreviousKnockoutStep() {
    if (!canGoPreviousKnockoutStep.value) {
        return;
    }

    if (selectedKnockoutStage.value !== 'FINAL' && Number(selectedKnockoutLeg.value) === 2) {
        selectedKnockoutLeg.value = 1;
        return;
    }

    const previousStage = knockoutStageList.value[selectedKnockoutStageIndex.value - 1];

    selectedKnockoutStage.value = previousStage;
    selectedKnockoutLeg.value = previousStage === 'FINAL' ? 1 : 2;
}

function goToNextKnockoutStep() {
    if (!canGoNextKnockoutStep.value) {
        return;
    }

    if (selectedKnockoutStage.value !== 'FINAL' && Number(selectedKnockoutLeg.value) === 1) {
        selectedKnockoutLeg.value = 2;
        return;
    }

    const nextStage = knockoutStageList.value[selectedKnockoutStageIndex.value + 1];

    selectedKnockoutStage.value = nextStage;
    selectedKnockoutLeg.value = 1;
}

function canPlayFixture(match) {
    return activeMatchIds.value.includes(match.id);
}

function isPlayed(match) {
    return match.home_score !== null && match.away_score !== null;
}

function extractMatchesFromState(state) {
    return [
        ...(state.matches ?? []),
        ...Object.values(state.knockout_matches ?? {}).flat(),
    ];
}

function formatStage(stage) {
    return stage
        ? stage.replaceAll('_', ' ').toLowerCase().replace(/\b\w/g, (letter) => letter.toUpperCase())
        : 'Knockout';
}

function wait(ms) {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

onMounted(loadLeague);
</script>

<template>
    <main class="min-h-screen bg-zinc-950 text-white">
        <section class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <header class="grid gap-2 border-b border-zinc-800 pb-5">
                <!-- <p class="text-sm font-semibold uppercase tracking-[0.25em] text-emerald-400">
                    Insider One
                </p> -->
                <h1 class="text-3xl font-bold tracking-normal sm:text-4xl">
                    Champions League Simulator
                </h1>
            </header>

            <TournamentStatusBar
                :label="statusLabel"
                :play-next-label="playNextLabel"
                :champion="champion"
                :disabled="actionInProgress"
                :play-next-disabled="currentStage === STAGE_FINISHED"
                :can-go-previous="topBarCanPrevious"
                :can-go-next="topBarCanNext"
                @previous="goToPreviousStep"
                @next="goToNextStep"
                @play-next="playNextWithModal"
                @play-all="playAllWeeks"
                @reset="resetFixtures"
            />

            <div
                v-if="error"
                class="rounded-lg border border-red-900/80 bg-red-950/50 px-4 py-3 text-sm text-red-100"
                role="alert"
            >
                {{ error }}
            </div>

            <UiCard
                v-if="isLoading"
                class="p-6 text-sm text-zinc-300"
            >
                Loading tournament state...
            </UiCard>

            <UiCard
                v-else-if="!hasLeagueData"
                class="border-dashed border-zinc-700 p-8"
            >
                <h2 class="text-lg font-semibold text-white">
                    No tournament data
                </h2>
                <p class="mt-2 max-w-xl text-sm text-zinc-400">
                    Reset the tournament to generate fixtures.
                </p>
            </UiCard>

            <GroupStagePanel
                v-else-if="isViewingGroupStage"
                :selected-group="selectedGroup"
                :selected-week="selectedWeek"
                :standings="selectedStandings"
                :predictions="selectedPredictions"
                :matches="selectedWeekMatches"
                :disabled="actionInProgress"
                :can-play-match="canPlayFixture"
                :groups="availableGroups"
                :selected-group-id="selectedGroupId"
                @play-match="playSingleMatch"
                @save-result="saveResult"
                @update:selected-group-id="selectedGroupId = $event"
            />

            <KnockoutStagePanel
                v-else
                :summaries="knockoutSummaries"
                :matches="selectedKnockoutMatches"
                :current-stage="currentStage"
                :selected-stage="selectedKnockoutStage"
                :selected-leg="selectedKnockoutLeg"
                :predictions="selectedTournamentWinnerPredictions"
                :champion="champion"
                :disabled="actionInProgress"
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
