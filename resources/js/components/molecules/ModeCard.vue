<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    actionLabel: {
        type: String,
        required: true,
    },
    tone: {
        type: String,
        default: 'emerald',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['select']);
</script>

<template>
    <button
        type="button"
        class="group grid gap-3 rounded-xl border border-zinc-800 bg-zinc-900/70 p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:bg-zinc-900 hover:shadow-lg hover:shadow-zinc-950/20"
        :class="[
            compact ? 'min-h-0' : 'min-h-48',
            tone === 'sky' ? 'hover:border-sky-500/60' : 'hover:border-emerald-500/60',
        ]"
        @click="$emit('select')"
    >
        <span class="flex items-start gap-3">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-zinc-950"
                :class="tone === 'sky' ? 'bg-sky-400' : 'bg-emerald-500'"
            >
                <slot name="icon" />
            </span>

            <span class="min-w-0">
                <span class="block text-lg font-semibold tracking-normal text-white">{{ title }}</span>
                <span class="mt-1 block text-sm leading-5 text-zinc-400">
                    {{ description }}
                </span>
            </span>
        </span>

        <span
            class="mt-1 inline-flex items-center gap-2 text-sm font-semibold"
            :class="tone === 'sky' ? 'text-sky-300' : 'text-emerald-300'"
        >
            {{ actionLabel }}
            <slot name="action-icon" />
        </span>
    </button>
</template>
