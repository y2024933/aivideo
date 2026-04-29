<script setup>
defineProps({
    loading: { type: Boolean, default: false },
    variant: { type: String, default: 'primary' },
    disabled: { type: Boolean, default: false },
})

defineEmits(['click'])

const variants = {
    primary: 'bg-indigo-600 text-white hover:bg-indigo-700 disabled:bg-indigo-300',
    success: 'bg-green-600 text-white hover:bg-green-700 disabled:bg-green-300',
    danger: 'bg-red-600 text-white hover:bg-red-700 disabled:bg-red-300',
    secondary: 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 disabled:bg-gray-100',
}
</script>

<template>
    <button
        :class="['px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2', variants[variant]]"
        :disabled="loading || disabled"
        @click="$emit('click')"
    >
        <svg v-if="loading" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <slot />
    </button>
</template>
