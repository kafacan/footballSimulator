<script setup>
import { reactive, watch } from 'vue';
import { Save, X } from 'lucide-vue-next';
import ModalShell from '../atoms/ModalShell.vue';
import UiButton from '../atoms/UiButton.vue';
import ScoreInput from '../atoms/ScoreInput.vue';
const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    match: {
        type: Object,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'save']);

const form = reactive({
    home_score: '',
    away_score: '',
});

watch(
    () => [props.open, props.match.home_score, props.match.away_score],
    () => {
        form.home_score = props.match.home_score ?? '';
        form.away_score = props.match.away_score ?? '';
    },
    { immediate: true },
);

function save() {
    emit('save', {
        home_score: Number(form.home_score),
        away_score: Number(form.away_score),
    });
}

function close() {
    emit('close');
}
</script>

<template>
    <ModalShell
        :open="open"
        title="Edit Result"
        :subtitle="`${match.home_team?.name} vs ${match.away_team?.name}`"
        close-label="Close edit dialog"
        @close="close"
    >
        <div class="grid gap-8">
            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-5">
                <div class="text-right">
                    <p class="truncate text-sm font-semibold text-zinc-300">
                        {{ match.home_team?.name }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <ScoreInput
                        v-model="form.home_score"
                        :disabled="disabled"
                    />

                    <span class="text-lg font-bold text-zinc-600">
                        -
                    </span>

                    <ScoreInput
                        v-model="form.away_score"
                        :disabled="disabled"
                    />
                </div>

                <div>
                    <p class="truncate text-sm font-semibold text-zinc-300">
                        {{ match.away_team?.name }}
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3">
                <UiButton
                    variant="secondary"
                    :disabled="disabled"
                    tooltip="Action in progress"
                    @click="close"
                >
                    Cancel
                </UiButton>

                <UiButton
                    variant="primary"
                    :disabled="disabled || form.home_score === '' || form.away_score === ''"
                    tooltip="Enter both scores"
                    :icon-left="Save"
                    @click="save"
                >
                    Save Result
                </UiButton>
            </div>
        </template>
    </ModalShell>
</template>
