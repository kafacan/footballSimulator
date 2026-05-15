<script setup>
import { computed } from 'vue';
import UiCard from '../atoms/UiCard.vue';
import MatchCard from '../molecules/MatchCard.vue';

const props = defineProps({
    summaries: {
        type: Object,
        default: () => ({}),
    },
    matches: {
        type: Array,
        default: () => [],
    },
    currentStage: {
        type: String,
        default: 'GROUP_STAGE',
    },
    selectedStage: {
        type: String,
        default: '',
    },
    selectedLeg: {
        type: Number,
        default: 1,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['play-match', 'save-result']);

const activeStage = computed(() => props.selectedStage || props.currentStage);
const activeSummaries = computed(() => props.summaries[activeStage.value] ?? []);

function isPlayed(match) {
    return match?.home_score !== null && match?.away_score !== null;
}

function canPlay(match) {
    return activeStage.value === props.currentStage && !isPlayed(match);
}

function matchForSummary(summary) {
    return props.matches.find((match) => {
        if (match.pairing_key && summary.pairing_key) {
            return match.pairing_key === summary.pairing_key;
        }

        const summaryTeamIds = summary.teams
            .map((team) => Number(team.team_id))
            .sort()
            .join('-');

        const matchTeamIds = [match.home_team?.id, match.away_team?.id]
            .map(Number)
            .sort()
            .join('-');

        return summaryTeamIds === matchTeamIds;
    }) ?? null;
}

function aggregateLabel(summary) {
    if (Number(props.selectedLeg) !== 2 || !summary.complete) {
        return '';
    }

    const [first, second] = summary.teams ?? [];

    if (!first || !second) {
        return '';
    }

    return `${first.aggregate}-${second.aggregate} agg`;
}

function label(stage) {
    return stage
        ? stage.replaceAll('_', ' ').toLowerCase().replace(/\b\w/g, (letter) => letter.toUpperCase())
        : 'Knockout';
}
</script>

<template>
    <UiCard>
        <div class="border-b border-zinc-800 px-4 py-3">
            <h2 class="text-base font-semibold text-white">
                {{ label(activeStage) }} Matches
            </h2>
            <p class="mt-1 text-xs text-zinc-500">
                {{ activeStage === 'FINAL' ? 'Final result' : `Leg ${selectedLeg}` }}
            </p>
        </div>

        <div class="p-4">
            <div
                v-if="activeSummaries.length"
                class="grid gap-3"
                :class="activeStage === 'FINAL' ? 'md:grid-cols-1' : 'md:grid-cols-2'"
            >
                <MatchCard
                    v-for="summary in activeSummaries"
                    :key="summary.pairing_key"
                    :match="matchForSummary(summary)"
                    :disabled="disabled"
                    :winner-id="Number(selectedLeg) === 2 ? summary.winner_id : null"
                    :aggregate-label="aggregateLabel(summary)"
                    :can-play="canPlay(matchForSummary(summary))"
                    @play="(matchId) => emit('play-match', matchId)"
                    @save="(matchId, scores) => emit('save-result', matchId, scores)"
                />
            </div>

            <p
                v-else
                class="rounded-xl border border-dashed border-zinc-800 bg-zinc-950/40 p-5 text-sm text-zinc-500"
            >
                Knockout matches will appear after the group stage is completed.
            </p>
        </div>
    </UiCard>
</template>
