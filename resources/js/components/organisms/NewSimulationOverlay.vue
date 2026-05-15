<script setup>
import { CirclePlay, Flag, Trophy, Users, X } from 'lucide-vue-next';
import IconButton from '../atoms/IconButton.vue';
import LoadingSpinner from '../atoms/LoadingSpinner.vue';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    creatingUclSession: {
        type: Boolean,
        default: false,
    },
    dismissible: {
        type: Boolean,
        default: true,
    },
});

defineEmits(['close', 'create-ucl', 'navigate-national']);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <section
                v-if="open"
                class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/85 px-4 text-white backdrop-blur-md"
                role="dialog"
                aria-modal="true"
            >
                <div class="grid w-full max-w-5xl gap-6">
                    <header class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-400">
                                New Simulation
                            </p>
                            <h2 class="mt-2 text-3xl font-semibold tracking-normal text-white">
                                Choose competition format
                            </h2>
                            <p class="mt-2 max-w-xl text-sm leading-6 text-zinc-400">
                                {{ dismissible ? 'Start a fresh run. The current simulations stay untouched.' : 'Choose a format to create your first simulation.' }}
                            </p>
                        </div>

                        <IconButton
                            v-if="dismissible"
                            :icon="X"
                            aria-label="Close new simulation"
                            size="lg"
                            class="border border-zinc-800 bg-zinc-900/80"
                            @click="$emit('close')"
                        />
                    </header>

                    <div class="grid gap-4 md:grid-cols-2">
                        <button
                            type="button"
                            class="group min-h-72 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 text-left transition duration-200 hover:-translate-y-1 hover:border-emerald-500/70 hover:bg-zinc-900 hover:shadow-2xl hover:shadow-emerald-950/30"
                            :disabled="creatingUclSession"
                            @click="$emit('create-ucl')"
                        >
                            <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-emerald-500 text-zinc-950">
                                <LoadingSpinner
                                    v-if="creatingUclSession"
                                    class="h-6 w-6"
                                />
                                <Trophy v-else class="h-7 w-7" />
                            </span>

                            <span class="mt-8 block">
                                <span class="block text-2xl font-semibold tracking-normal text-white">
                                    Champions League
                                </span>
                                <span class="mt-3 block max-w-sm text-sm leading-6 text-zinc-400">
                                    Group phase, knockout nights, predictions, and a full champion path.
                                </span>
                            </span>

                            <span class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-emerald-300">
                                {{ creatingUclSession ? 'Creating simulation' : 'Start simulation' }}
                                <CirclePlay class="h-4 w-4 transition group-hover:translate-x-1" />
                            </span>
                        </button>

                        <button
                            type="button"
                            class="group min-h-72 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 text-left transition duration-200 hover:-translate-y-1 hover:border-sky-500/70 hover:bg-zinc-900 hover:shadow-2xl hover:shadow-sky-950/30"
                            @click="$emit('navigate-national')"
                        >
                            <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-sky-400 text-zinc-950">
                                <Flag class="h-7 w-7" />
                            </span>

                            <span class="mt-8 block">
                                <span class="block text-2xl font-semibold tracking-normal text-white">
                                    National League
                                </span>
                                <span class="mt-3 block max-w-sm text-sm leading-6 text-zinc-400">
                                    Pick teams, build a single table, and simulate the season week by week.
                                </span>
                            </span>

                            <span class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-sky-300">
                                Select teams
                                <Users class="h-4 w-4 transition group-hover:translate-x-1" />
                            </span>
                        </button>
                    </div>
                </div>
            </section>
        </Transition>
    </Teleport>
</template>
