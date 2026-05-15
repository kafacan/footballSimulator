<script setup>
import { Plus, Trash2 } from 'lucide-vue-next';
import UiButton from '../atoms/UiButton.vue';
import IconButton from '../atoms/IconButton.vue';
import EmptyState from '../atoms/EmptyState.vue';
import SectionHeader from '../molecules/SectionHeader.vue';
import SessionListItem from './SessionListItem.vue';

defineProps({
    sessions: {
        type: Array,
        default: () => [],
    },
    deletingSessionId: {
        type: [Number, String, null],
        default: null,
    },
    clearing: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['new-simulation', 'clear-all', 'resume', 'delete']);
</script>

<template>
    <section class="grid gap-3">
        <SectionHeader
            title="Simulations"
            subtitle="Continue, review, or clear your tournament runs."
        >
            <template #actions>
                <UiButton
                    :icon-left="Plus"
                    variant="primary"
                    aria-label="New simulation"
                    size="md"
                    @click="$emit('new-simulation')"
                >Create Simulation</UiButton>
                <UiButton
                    v-if="sessions.length"
                    variant="danger"
                    :icon-left="Trash2"
                    :loading="clearing"
                    @click="$emit('clear-all')"
                >
                    Delete All
                </UiButton>
            </template>
        </SectionHeader>

        <div v-if="sessions.length" class="grid gap-3 md:grid-cols-2">
            <SessionListItem
                v-for="session in sessions"
                :key="session.id"
                :session="session"
                :deleting="deletingSessionId === session.id"
                @resume="$emit('resume', session)"
                @delete="$emit('delete', session)"
            />
        </div>

        <EmptyState
            v-else
            title="No simulations yet."
            description="Start a competition to create your first simulation."
        />
    </section>
</template>
