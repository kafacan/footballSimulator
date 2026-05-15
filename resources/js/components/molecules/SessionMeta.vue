<script setup>
defineProps({
    createdAt: {
        type: String,
        default: '',
    },
    stage: {
        type: String,
        default: '',
    },
    weekLabel: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-400">
        <template v-for="(item, index) in [createdAt, stage, weekLabel].filter(Boolean)" :key="item">
            <span v-if="index > 0" class="text-zinc-600">·</span>
            <span>{{ item }}</span>
        </template>
    </div>
</template>
