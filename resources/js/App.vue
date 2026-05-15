<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import DashboardPage from './pages/DashboardPage.vue';
import SessionPage from './pages/SessionPage.vue';
import NationalGameModeSetupPage from './pages/NationalGameModeSetupPage.vue';

const routes = new Set([
    '/',
    '/national-league/setup',
]);

const currentPath = ref(normalizePath(window.location.pathname));
const nationalSelectedCount = ref(4);
const maxNationalTeams = 18;

const normalizedPath = computed(() => currentPath.value);
const isDashboardRoute = computed(() => normalizedPath.value === '/');
const isNationalSetupRoute = computed(() => normalizedPath.value === '/national-league/setup');
const sessionId = computed(() => normalizedPath.value.match(/^\/sessions\/(\d+)$/)?.[1] ?? null);

function navigate(path, options = {}) {
    const normalized = normalizePath(path);
    const method = options.replace ? 'replaceState' : 'pushState';

    window.history[method]({}, '', normalized);
    currentPath.value = normalized;
}

function normalizePath(path) {
    const withoutTrailingSlash = path.length > 1 ? path.replace(/\/+$/, '') : path;

    if (routes.has(withoutTrailingSlash) || /^\/sessions\/\d+$/.test(withoutTrailingSlash)) {
        return withoutTrailingSlash;
    }

    return '/';
}

function handlePopState() {
    currentPath.value = normalizePath(window.location.pathname);
}

onMounted(() => {
    window.addEventListener('popstate', handlePopState);

    if (currentPath.value !== normalizePath(window.location.pathname)) {
        navigate(currentPath.value, { replace: true });
    }
});

onUnmounted(() => {
    window.removeEventListener('popstate', handlePopState);
});
</script>

<template>
    <DashboardPage
        v-if="isDashboardRoute"
        :national-selected-count="nationalSelectedCount"
        :max-national-teams="maxNationalTeams"
        @navigate="navigate"
    />

    <NationalGameModeSetupPage
        v-else-if="isNationalSetupRoute"
        @navigate="navigate"
        @selected-count-change="nationalSelectedCount = $event"
    />

    <SessionPage
        v-else-if="sessionId"
        :session-id="sessionId"
        @navigate="navigate"
    />
</template>