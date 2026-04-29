<script setup>
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
                <RouterLink to="/" class="text-lg font-bold text-gray-900">
                    AI Video
                </RouterLink>
                <RouterLink
                    to="/cases/new"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700 transition"
                >
                    新建案
                </RouterLink>
            </div>
        </header>
        <main class="max-w-5xl mx-auto px-4 py-6">
            <RouterView />
        </main>
    </div>
</template>
