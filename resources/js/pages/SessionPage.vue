<script setup>
import { onMounted, ref } from 'vue';
import { ArrowLeft } from 'lucide-vue-next';
import UiCard from '../components/atoms/UiCard.vue';
import UiButton from '../components/atoms/UiButton.vue';
import LeaguePage from './UCLGameModeSessionPage.vue';
import NationalGameModeSessionPage from './NationalGameModeSessionPage.vue';
import { getGameSessionState } from '../services/leagueApi';

const props = defineProps({
    sessionId: {
        type: [Number, String],
        required: true,
    },
});

defineEmits(['navigate']);

const session = ref(null);
const isLoading = ref(true);
const error = ref('');

async function loadSessionMode() {
    isLoading.value = true;
    error.value = '';

    try {
        const state = await getGameSessionState(props.sessionId);
        session.value = state.session;
    } catch (exception) {
        error.value = exception.message;
    } finally {
        isLoading.value = false;
    }
}

onMounted(loadSessionMode);
</script>

<template>
    <main v-if="isLoading" class="min-h-screen bg-zinc-950 text-white">
        <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <UiCard class="p-6 text-sm text-zinc-300">
                Loading session...
            </UiCard>
        </section>
    </main>

    <main v-else-if="error" class="min-h-screen bg-zinc-950 text-white">
        <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <UiCard class="border-red-900/80 bg-red-950/50 px-4 py-3 text-sm text-red-100">
                {{ error }}
            </UiCard>
        </section>
    </main>

    <section v-else class="min-h-screen bg-zinc-950">
        <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
            <UiButton :icon-left="ArrowLeft" @click="$emit('navigate', '/')">
                Dashboard
            </UiButton>
        </div>
        <LeaguePage v-if="session?.mode === 'champions_league'" :session-id="sessionId" />
        <NationalGameModeSessionPage
            v-else
            :session-id="sessionId"
            @navigate="$emit('navigate', $event)"
        />
    </section>

    
</template>
