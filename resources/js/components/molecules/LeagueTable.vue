<script setup>
import UiCard from '../atoms/UiCard.vue';
import UiBadge from '../atoms/UiBadge.vue';
import StepNavigator from './StepNavigator.vue';

defineProps({
    title: {
        type: String,
        default: 'League Table',
    },
    standings: {
        type: Array,
        default: () => [],
    },
    canChangeGroup: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['previous-group', 'next-group']);
</script>

<template>
    <UiCard>
        <div class="grid gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <StepNavigator
                    v-if="canChangeGroup"
                    :label="title"
                    :can-previous="true"
                    :can-next="true"
                    :disabled="disabled"
                    @previous="$emit('previous-group')"
                    @next="$emit('next-group')"
                />

                <h2
                    v-else
                    class="text-base font-semibold text-white"
                >
                    {{ title }}
                </h2>
            </div>

            <div v-if="standings.length" class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="bg-zinc-950/70 text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="w-12 px-4 py-3">#</th>
                            <th class="px-4 py-3">Team</th>
                            <th class="px-3 py-3 text-right">P</th>
                            <th class="px-3 py-3 text-right">W</th>
                            <th class="px-3 py-3 text-right">D</th>
                            <th class="px-3 py-3 text-right">L</th>
                            <th class="px-3 py-3 text-right">GF</th>
                            <th class="px-3 py-3 text-right">GA</th>
                            <th class="px-3 py-3 text-right">GD</th>
                            <th class="px-4 py-3 text-right">Pts</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-800">
                        <tr
                            v-for="(row, index) in standings"
                            :key="row.team_id"
                            class="text-zinc-300"
                            :class="index < 2 ? 'bg-emerald-950/20' : ''"
                        >
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                    :class="index < 2 ? 'bg-emerald-500 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                                >
                                    {{ index + 1 }}
                                </span>
                            </td>

                            <td class="px-4 py-3 font-semibold text-white">
                                {{ row.team }}
                            </td>

                            <td class="px-3 py-3 text-right">{{ row.played }}</td>
                            <td class="px-3 py-3 text-right">{{ row.won }}</td>
                            <td class="px-3 py-3 text-right">{{ row.drawn }}</td>
                            <td class="px-3 py-3 text-right">{{ row.lost }}</td>
                            <td class="px-3 py-3 text-right">{{ row.goals_for }}</td>
                            <td class="px-3 py-3 text-right">{{ row.goals_against }}</td>
                            <td class="px-3 py-3 text-right">
                                {{ row.goal_difference > 0 ? `+${row.goal_difference}` : row.goal_difference }}
                            </td>
                            <td class="px-4 py-3 text-right text-base font-bold text-emerald-300">
                                {{ row.points }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="px-4 py-6 text-sm text-zinc-400">
                No standings available.
            </p>
        </div>
    </UiCard>
</template>
