<script setup>
import { computed, onMounted, ref } from 'vue';
import { ArrowLeft, Calendar } from 'lucide-vue-next';
import UiButton from '../components/atoms/UiButton.vue';
import NationalTeamSelection from '../components/organisms/NationalTeamSelection.vue';
import { createGameSession, getTeams } from '../services/leagueApi';

const MIN_NATIONAL_TEAMS = 4;
const MAX_NATIONAL_TEAMS = 18;

const props = defineProps({
    mode: {
        type: String,
        default: 'national_league',
    },
});

const emit = defineEmits(['navigate', 'selected-count-change']);

const teams = ref([]);
const selectedTeamIds = ref([]);
const isLoading = ref(true);
const error = ref('');
const isCreating = ref(false);

const selectedCount = computed(() => selectedTeamIds.value.length);
const hasEvenTeamCount = computed(() => selectedCount.value % 2 === 0);
const canConfirm = computed(() => (
    (selectedTeamIds.value.length >= MIN_NATIONAL_TEAMS && hasEvenTeamCount.value)
));

function toggleTeam(teamId) {
    error.value = '';

    if (selectedTeamIds.value.includes(teamId)) {
        if (selectedTeamIds.value.length <= MIN_NATIONAL_TEAMS) {
            error.value = `Select at least ${MIN_NATIONAL_TEAMS} teams.`;
            return;
        }

        selectedTeamIds.value = selectedTeamIds.value.filter((id) => id !== teamId);
        emit('selected-count-change', selectedTeamIds.value.length);
        return;
    }

    if (selectedTeamIds.value.length >= MAX_NATIONAL_TEAMS) {
        error.value = `National league supports up to ${MAX_NATIONAL_TEAMS} teams.`;
        return;
    }

    selectedTeamIds.value = [...selectedTeamIds.value, teamId];
    emit('selected-count-change', selectedTeamIds.value.length);
}

async function createSession() {
    error.value = '';

    if (!canConfirm.value) {
        error.value = hasEvenTeamCount.value
            ? `Select at least ${MIN_NATIONAL_TEAMS} teams.`
            : 'Select an even number of teams.';
        return;
    }

    isCreating.value = true;

    try {
        const payload = { mode: 'national_league', team_ids: selectedTeamIds.value };
        const data = await createGameSession(payload);

        emit('navigate', `/sessions/${data.session.id}`);
    } catch (exception) {
        error.value = exception.message;
    } finally {
        isCreating.value = false;
    }
}

async function loadTeams() {
    isLoading.value = true;

    try {
        const data = await getTeams();
        teams.value = data.teams ?? [];
        selectedTeamIds.value = teams.value.slice(0, MIN_NATIONAL_TEAMS).map((team) => team.id);
        emit('selected-count-change', selectedTeamIds.value.length);
    } catch (exception) {
        error.value = exception.message;
    } finally {
        isLoading.value = false;
    }
}

onMounted(loadTeams);
</script>

<template>
    <NationalTeamSelection
        :teams="teams"
        :selected-team-ids="selectedTeamIds"
        :selected-count="selectedCount"
        :min-teams="MIN_NATIONAL_TEAMS"
        :max-teams="MAX_NATIONAL_TEAMS"
        :error="error"
        :is-loading="isLoading"
        :can-confirm="canConfirm && !isCreating"
        :requires-even-count="true"
        @back="$emit('navigate', '/')"
        @confirm="createSession"
        @toggle-team="toggleTeam"
    />
</template>
