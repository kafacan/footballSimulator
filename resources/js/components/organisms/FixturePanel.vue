<script setup>
import UiCard from '../atoms/UiCard.vue';
import EmptyState from '../atoms/EmptyState.vue';
import SectionHeader from '../molecules/SectionHeader.vue';
import MatchCard from '../molecules/MatchCard.vue';

defineProps({
    title: {
        type: String,
        default: 'Fixtures',
    },
    subtitle: {
        type: String,
        default: '',
    },
    emptyText: {
        type: String,
        default: 'No fixtures available.',
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

const emit = defineEmits(['play-match', 'save-result']);
</script>

<template>
    <UiCard>
        <div class="grid gap-4 p-4">
            <SectionHeader
                :title="title"
                :subtitle="subtitle"
            />

            <div v-if="matches.length" class="grid gap-3">
                <MatchCard
                    v-for="match in matches"
                    :key="match.id"
                    :match="match"
                    :disabled="disabled"
                    :can-play="canPlayMatch(match)"
                    @play="(matchId) => emit('play-match', matchId)"
                    @save="(matchId, scores) => emit('save-result', matchId, scores)"
                />
            </div>

            <EmptyState
                v-else
                :description="emptyText"
            />
        </div>
    </UiCard>
</template>
