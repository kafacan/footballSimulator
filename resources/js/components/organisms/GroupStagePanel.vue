<script setup>
import { computed } from 'vue';
import FixturePanel from './FixturePanel.vue';
import LeagueTable from '../molecules/LeagueTable.vue';
import PredictionPanel from '../molecules/PredictionPanel.vue';

const props = defineProps({
    groups: {
        type: Array,
        default: () => [],
    },
    selectedGroupId: {
        type: [Number, String, null],
        default: null,
    },
    selectedGroup: {
        type: Object,
        default: null,
    },
    selectedWeek: {
        type: Number,
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
    matches: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    canPlayMatch: {
        type: Function,
        default: () => true,
    },
});

const emit = defineEmits([
    'update:selected-group-id',
    'play-match',
    'save-result',
]);

const canChangeGroup = computed(() => props.groups.length > 1);
const selectedGroupIndex = computed(() => props.groups.findIndex((group) => (
    Number(group.id) === Number(props.selectedGroupId)
)));

function selectGroupByOffset(offset) {
    if (!canChangeGroup.value) {
        return;
    }

    const currentIndex = selectedGroupIndex.value >= 0 ? selectedGroupIndex.value : 0;
    const nextIndex = (currentIndex + offset + props.groups.length) % props.groups.length;

    emit('update:selected-group-id', props.groups[nextIndex].id);
}
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
            <div class="grid content-start gap-6">
                <LeagueTable
                    :title="selectedGroup ? `${selectedGroup.name}` : 'League Table'"
                    :standings="standings"
                    :can-change-group="canChangeGroup"
                    :disabled="disabled"
                    @previous-group="selectGroupByOffset(-1)"
                    @next-group="selectGroupByOffset(1)"
                />

                <PredictionPanel
                    title="Group Predictions"
                    :predictions="predictions"
                />
            </div>

            <FixturePanel
                :title="selectedGroup ? `${selectedGroup.name} Fixtures` : 'Fixtures'"
                :subtitle="selectedWeek ? `Week ${selectedWeek}` : ''"
                empty-text="No fixtures for this group/week."
                :matches="matches"
                :disabled="disabled"
                :can-play-match="canPlayMatch"
                @play-match="(matchId) => emit('play-match', matchId)"
                @save-result="(matchId, scores) => emit('save-result', matchId, scores)"
            />
        </div>
    </div>
</template>
