<script setup>
const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

function handleInput(event) {
    const value = event.target.value;

    if (value === '') {
        emit('update:modelValue', '');
        return;
    }

    const number = Math.max(0, Math.min(9, Number(value)));

    emit('update:modelValue', Number.isNaN(number) ? '' : number);
}
</script>

<template>
    <label class="grid gap-2">
        <input
            :value="modelValue"
            type="number"
            min="0"
            max="9"
            inputmode="numeric"
            :disabled="disabled"
            class="h-16 w-16 rounded-2xl border border-zinc-700 bg-zinc-950 text-center text-2xl font-black text-white outline-none transition focus:border-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
            @input="handleInput"
        >
    </label>
</template>