<script setup>
import { ArrowLeft, Calendar, Check, Shield } from 'lucide-vue-next';
import UiButton from '../atoms/UiButton.vue';

defineProps({
    teams: {
        type: Array,
        default: () => [],
    },
    selectedTeamIds: {
        type: Array,
        default: () => [],
    },
    selectedCount: {
        type: Number,
        default: 0,
    },
    minTeams: {
        type: Number,
        required: true,
    },
    maxTeams: {
        type: Number,
        required: true,
    },
    error: {
        type: String,
        default: '',
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    canConfirm: {
        type: Boolean,
        default: false,
    },
    requiresEvenCount: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['back', 'confirm', 'toggle-team']);
</script>

<template>
    <main class="min-h-screen bg-zinc-950 text-white">
        <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
            <UiButton :icon-left="ArrowLeft" @click="$emit('back')">
                Dashboard
            </UiButton>
        </div>
        <section class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-4 border-b border-zinc-800 pb-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="grid gap-2">
                    <h1 class="text-3xl font-bold tracking-normal sm:text-4xl">
                        Select National League Teams
                    </h1>
                    <p class="max-w-2xl text-sm leading-6 text-zinc-400">
                        Four teams are selected by default. You can select an even number up to {{ maxTeams }} teams.
                    </p>
                </div>
                <UiButton
                    variant="primary"
                    :icon-left="Calendar"
                    :disabled="!canConfirm"
                    @click="$emit('confirm')"
                >
                    Confirm League
                </UiButton>
            </header>

            <div
                v-if="error"
                class="rounded-lg border border-red-900/80 bg-red-950/50 px-4 py-3 text-sm text-red-100"
                role="alert"
            >
                {{ error }}
            </div>

            <section class="rounded-lg border border-zinc-800 bg-zinc-900/70 p-4">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">
                        {{ selectedCount }} selected
                    </h2>
                    <span class="rounded-lg bg-zinc-950 px-3 py-2 text-sm font-semibold text-sky-300">
                        Min {{ minTeams }} · Max {{ maxTeams }}<template v-if="requiresEvenCount"> · Even only</template>
                    </span>
                </div>

                <p
                    v-if="requiresEvenCount && selectedCount % 2 === 1"
                    class="mb-4 rounded-lg border border-amber-900/70 bg-amber-950/40 px-4 py-3 text-sm text-amber-100"
                >
                    Select one more team or remove one team to keep the league count even.
                </p>

                <div v-if="isLoading" class="rounded-lg border border-zinc-800 bg-zinc-950 p-4 text-sm text-zinc-400">
                    Loading teams...
                </div>

                <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="team in teams"
                        :key="team.id"
                        type="button"
                        class="flex min-h-20 items-center justify-between gap-3 rounded-lg border px-3 py-3 text-left text-sm transition"
                        :class="selectedTeamIds.includes(team.id)
                            ? 'border-sky-400/70 bg-sky-400/10 text-white'
                            : 'border-zinc-800 bg-zinc-950 text-zinc-300 hover:border-zinc-600'"
                        @click="$emit('toggle-team', team.id)"
                    >
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-zinc-300">
                                <Shield class="h-4 w-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ team.name }}</span>
                                <span class="text-xs text-zinc-500">Power {{ team.power }}</span>
                            </span>
                        </span>
                        <span
                            v-if="selectedTeamIds.includes(team.id)"
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-sky-400 text-zinc-950"
                        >
                            <Check class="h-4 w-4" />
                        </span>
                    </button>
                </div>
            </section>
        </section>
    </main>
</template>
