<script setup>
import { computed, onMounted, ref } from 'vue';
import DashboardHeader from '../components/organisms/DashboardHeader.vue';
import NewSimulationOverlay from '../components/organisms/NewSimulationOverlay.vue';
import SessionList from '../components/organisms/SessionList.vue';
import { clearGameSessions, createGameSession, deleteGameSession, getGameSessions } from '../services/leagueApi';

defineProps({
    nationalSelectedCount: {
        type: Number,
        default: 4,
    },
    maxNationalTeams: {
        type: Number,
        default: 18,
    },
});

const emit = defineEmits(['navigate']);

const sessions = ref([]);
const sessionsError = ref('');
const deletingSessionId = ref(null);
const isCreatingUclSession = ref(false);
const isClearingSessions = ref(false);
const isNewSimulationOpen = ref(false);
const hasLoadedSessions = ref(false);

const requiresInitialSimulation = computed(() => hasLoadedSessions.value && sessions.value.length === 0 && !sessionsError.value);
const isNewSimulationOverlayOpen = computed(() => isNewSimulationOpen.value || requiresInitialSimulation.value);
const dashboardStats = computed(() => [
    { label: 'Simulations', value: sessions.value.length },
    { label: 'Active', value: sessions.value.filter((session) => session.status === 'in_progress').length },
    { label: 'Finished', value: sessions.value.filter((session) => session.status === 'finished').length },
]);
const headerStats = computed(() => (sessions.value.length ? dashboardStats.value : []));

async function loadSessions() {
    sessionsError.value = '';

    try {
        const data = await getGameSessions();
        sessions.value = data.sessions ?? [];
        hasLoadedSessions.value = true;
    } catch (exception) {
        sessionsError.value = exception.message;
    }
}

async function removeSession(session) {
    if (!window.confirm(`Delete "${session.name}"?`)) {
        return;
    }

    sessionsError.value = '';
    deletingSessionId.value = session.id;

    try {
        await deleteGameSession(session.id);
        sessions.value = sessions.value.filter((item) => item.id !== session.id);
    } catch (exception) {
        sessionsError.value = exception.message;
    } finally {
        deletingSessionId.value = null;
    }
}

async function createUclSession() {
    sessionsError.value = '';
    isCreatingUclSession.value = true;

    try {
        const data = await createGameSession({ mode: 'champions_league' });

        emit('navigate', `/sessions/${data.session.id}`);
    } catch (exception) {
        sessionsError.value = exception.message;
    } finally {
        isCreatingUclSession.value = false;
    }
}

function resumeSession(session) {
    if (!session) {
        return;
    }

    emit('navigate', `/sessions/${session.id}`);
}

function openNewSimulation() {
    isNewSimulationOpen.value = true;
}

function closeNewSimulation() {
    if (requiresInitialSimulation.value) {
        return;
    }

    isNewSimulationOpen.value = false;
}

function navigateNationalSetup() {
    closeNewSimulation();
    emit('navigate', '/national-league/setup');
}

async function clearSessions() {
    if (!window.confirm('Delete all sessions?')) {
        return;
    }

    sessionsError.value = '';
    isClearingSessions.value = true;

    try {
        await clearGameSessions();
        sessions.value = [];
    } catch (exception) {
        sessionsError.value = exception.message;
    } finally {
        isClearingSessions.value = false;
    }
}

onMounted(loadSessions);
</script>

<template>
    <main class="min-h-screen bg-zinc-950 text-white">
        <section class="mx-auto grid max-w-7xl gap-5 px-4 py-5 sm:px-6 lg:px-8">
            <DashboardHeader
                :stats="headerStats"
            />

            <div
                v-if="sessionsError"
                class="rounded-lg border border-red-900/80 bg-red-950/50 px-4 py-3 text-sm text-red-100"
                role="alert"
            >
                {{ sessionsError }}
            </div>

            <SessionList
                v-if="sessions.length"
                :sessions="sessions"
                :deleting-session-id="deletingSessionId"
                :clearing="isClearingSessions"
                @new-simulation="openNewSimulation"
                @clear-all="clearSessions"
                @resume="resumeSession"
                @delete="removeSession"
            />
        </section>

        <NewSimulationOverlay
            :open="isNewSimulationOverlayOpen"
            :creating-ucl-session="isCreatingUclSession"
            :dismissible="!requiresInitialSimulation"
            @close="closeNewSimulation"
            @create-ucl="createUclSession"
            @navigate-national="navigateNationalSetup"
        />
    </main>
</template>
