<script setup>
import { computed } from 'vue';
import { Play, Trash2, Trophy } from 'lucide-vue-next';
import UiBadge from '../atoms/UiBadge.vue';
import UiButton from '../atoms/UiButton.vue';
import IconButton from '../atoms/IconButton.vue';
import SessionMeta from '../molecules/SessionMeta.vue';
import {
    lastPlayedLabel,
    modeLabel,
    stageLabel,
    statusLabel,
    weekLabel,
} from '../../utils/dashboardSession';

const props = defineProps({
    session: {
        type: Object,
        required: true,
    },
    deleting: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['resume', 'delete']);

const stage = computed(() => (props.session.mode === 'national_league' ? '' : stageLabel(props.session.current_stage)));
const metaWeekLabel = computed(() => weekLabel(props.session));
const lastPlayed = computed(() => lastPlayedLabel(props.session));
const statusTone = computed(() => {
    if (props.session.status === 'finished') {
        return 'emerald';
    }

    if (props.session.status === 'in_progress') {
        return 'sky';
    }

    return 'neutral';
});
</script>

<template>
    <article class="grid h-full gap-4 rounded-xl border border-zinc-800 bg-zinc-900/60 p-4 transition duration-200 hover:border-emerald-500/40 hover:bg-zinc-900">
        <div class="grid gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <UiBadge :tone='neutral'">
                    {{ modeLabel(session.mode) }}
                </UiBadge>
                <UiBadge :tone="statusTone">
                    {{ statusLabel(session) }}
                </UiBadge>
            </div>

            <div class="grid gap-1">
                <h3 class="truncate text-xl font-semibold tracking-normal text-white">
                    {{ session.name }}
                </h3>
                <div
                    v-if="session.status === 'finished'"
                    class="flex items-center gap-2 text-sm font-semibold text-emerald-300"
                >
                    <Trophy class="h-4 w-4" />
                    <span>{{ session.champion_team?.name ?? 'Winner decided' }}</span>
                </div>
                <SessionMeta
                    v-else
                    :created-at="lastPlayed ? `Last played ${lastPlayed}` : ''"
                    :stage="stage"
                    :week-label="metaWeekLabel"
                />
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-zinc-800 pt-3">
            <UiButton variant="primary" :icon-left="Play" @click="$emit('resume')">
                Resume
            </UiButton>
            <UiButton
                :icon-left="Trash2"
                variant="danger"
                aria-label="Delete simulation"
                :disabled="deleting"
                tooltip="Deleting simulation"
                @click="$emit('delete')"
            >Delete</UiButton>

        </div>
    </article>
</template>
