<script setup>
import { RouterLink } from 'vue-router'

defineProps({
    to: { type: [String, Object], default: null },
    as: { type: String, default: 'link' },
})
</script>

<template>
    <!-- 按鈕模式（例如登出） -->
    <button
        v-if="as === 'button'"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
    >
        <slot />
    </button>

    <!-- 連結模式 -->
    <RouterLink
        v-else
        :to="to"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
    >
        <slot />
    </RouterLink>
</template>
