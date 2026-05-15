<script setup>
import { computed } from 'vue';
import { X } from 'lucide-vue-next';
import { modalVariants } from '../../design/variants';
import IconButton from './IconButton.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'lg',
    },
    closeLabel: {
        type: String,
        default: 'Close',
    },
});

defineEmits(['close']);

const panelClass = computed(() => modalVariants.sizes[props.size] ?? modalVariants.sizes.lg);
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
            <div
                v-if="open"
                :class="modalVariants.overlay"
                role="dialog"
                aria-modal="true"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-2 scale-[0.98] opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-2 scale-[0.98] opacity-0"
                >
                    <section :class="[modalVariants.panel, panelClass]">
                        <header
                            v-if="title || subtitle || $slots.header"
                            class="flex items-start justify-between gap-4 border-b border-zinc-800 px-5 py-4"
                        >
                            <div class="min-w-0">
                                <slot name="header">
                                    <p
                                        v-if="title"
                                        class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-400"
                                    >
                                        {{ title }}
                                    </p>
                                    <h2 v-if="subtitle" class="mt-1 text-lg font-bold text-white">
                                        {{ subtitle }}
                                    </h2>
                                </slot>
                            </div>

                            <IconButton
                                :icon="X"
                                :aria-label="closeLabel"
                                :tooltip="closeLabel"
                                @click="$emit('close')"
                            />
                        </header>

                        <div class="px-5 py-6">
                            <slot />
                        </div>

                        <footer v-if="$slots.footer" class="border-t border-zinc-800 bg-zinc-950/40 px-5 py-4">
                            <slot name="footer" />
                        </footer>
                    </section>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
