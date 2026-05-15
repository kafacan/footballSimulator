<script setup>
defineProps({
    stats: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <header class="flex flex-col gap-4 border-b border-zinc-800 pb-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-400">
                Matchday Hub
            </p>
            <h1 class="mt-1 text-3xl font-semibold tracking-normal text-white sm:text-4xl">
                Football Simulator
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-400">
                Continue your latest simulation, review previous runs, or start a new competition.
            </p>
        </div>

        <div v-if="stats.length" class="grid gap-2 sm:grid-cols-3">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="min-w-28 rounded-lg border border-zinc-800 bg-zinc-900/70 px-3 py-2"
            >
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-zinc-500">
                    {{ stat.label }}
                </p>
                <p class="mt-1 text-lg font-semibold text-white">
                    {{ stat.value }}
                </p>
            </div>
        </div>
    </header>
</template>
