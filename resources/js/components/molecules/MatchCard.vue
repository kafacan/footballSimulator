<script setup>
import { computed, ref } from 'vue';
import { ArrowUpRight, Pencil, Play } from 'lucide-vue-next';
import UiButton from '../atoms/UiButton.vue';
import EditMatchResultModal from './EditMatchResultModal.vue';

const props = defineProps({
    match: {
        type: Object,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    canPlay: {
        type: Boolean,
        default: true,
    },
    winnerId: {
        type: [Number, String, null],
        default: null,
    },
    aggregateLabel: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['play', 'save']);

const isEditing = ref(false);

const homeName = computed(() => props.match.home_team?.name ?? 'Home');
const awayName = computed(() => props.match.away_team?.name ?? 'Away');
const homeId = computed(() => props.match.home_team?.id ?? props.match.home_team_id);
const awayId = computed(() => props.match.away_team?.id ?? props.match.away_team_id);

const hasScore = computed(() => props.match.home_score !== null && props.match.away_score !== null);
const homeWon = computed(() => Number(props.winnerId) === Number(homeId.value));
const awayWon = computed(() => Number(props.winnerId) === Number(awayId.value));

const actionLabel = computed(() => (hasScore.value ? 'Edit Result' : 'Simulate Game'));
const actionIcon = computed(() => (hasScore.value ? Pencil : Play));
const playDisabled = computed(() => props.disabled || (!hasScore.value && !props.canPlay));

const playTooltip = computed(() => {
    if (props.disabled) {
        return 'Action in progress';
    }

    if (!props.canPlay && !hasScore.value) {
        return 'Complete previous matches first';
    }

    return '';
});

function openEditor() {
    isEditing.value = true;
}

function closeEditor() {
    isEditing.value = false;
}

function handlePrimaryAction() {
    if (hasScore.value) {
        openEditor();
        return;
    }

    emit('play', props.match.id);
}

function saveEditedResult(scores) {
    emit('save', props.match.id, scores);
    isEditing.value = false;
}
</script>

<template>
    <article
        class="group relative overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950/70 p-4 transition duration-200 hover:border-emerald-800/80 hover:bg-zinc-950"
    >
        <div class="grid gap-2 transition duration-200 group-hover:scale-[0.99] group-hover:opacity-45">
            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4">
                <div class="min-w-0">
                    <div class="flex items-center justify-end gap-2">
                        <span class="min-w-4">
                            <ArrowUpRight
                                v-if="homeWon"
                                class="h-3.5 w-3.5 text-emerald-400"
                                aria-hidden="true"
                            />
                        </span>

                        <p class="truncate text-right text-sm font-semibold text-zinc-100">
                            {{ homeName }}
                        </p>
                    </div>
                </div>

                <div class="grid justify-items-center">
                    <div
                        v-if="hasScore"
                        class="flex items-center gap-2"
                    >
                        <span
                            class="inline-flex h-12 min-w-12 items-center justify-center rounded-xl bg-zinc-800 px-3 text-xl font-black text-white shadow-inner"
                        >
                            {{ match.home_score }}
                        </span>

                        <span class="text-sm font-bold text-zinc-500">-</span>

                        <span
                            class="inline-flex h-12 min-w-12 items-center justify-center rounded-xl bg-zinc-800 px-3 text-xl font-black text-white shadow-inner"
                        >
                            {{ match.away_score }}
                        </span>
                    </div>

                    <div
                        v-else
                        class="inline-flex h-12 min-w-20 items-center justify-center rounded-xl border border-zinc-800 bg-zinc-900 px-4 text-sm font-black uppercase text-zinc-400"
                    >
                        vs
                    </div>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-semibold text-zinc-100">
                            {{ awayName }}
                        </p>

                        <span class="min-w-4">
                            <ArrowUpRight
                                v-if="awayWon"
                                class="h-3.5 w-3.5 text-emerald-400"
                                aria-hidden="true"
                            />
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="aggregateLabel"
                class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4"
            >
                <div />

                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">
                    {{ aggregateLabel }}
                </p>

                <div />
            </div>
        </div>

        <div
            v-if="!isEditing"
            class="pointer-events-none absolute inset-0 grid place-items-center bg-zinc-950/20 opacity-0 backdrop-blur-[1px] transition duration-200 group-hover:opacity-100"
        >
            <UiButton
                class="pointer-events-auto translate-y-1 opacity-0 transition duration-200 group-hover:translate-y-0 group-hover:opacity-100"
                :variant="hasScore ? 'secondary' : 'primary'"
                :disabled="playDisabled"
                :tooltip="playTooltip"
                :icon-left="actionIcon"
                @click="handlePrimaryAction"
            >
                {{ actionLabel }}
            </UiButton>
        </div>

        <EditMatchResultModal
            :open="isEditing"
            :match="match"
            :disabled="disabled"
            @close="closeEditor"
            @save="saveEditedResult"
        />
    </article>
</template>
