<script setup>
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
    await auth.logout()
    router.push({ name: 'login' })
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header v-if="auth.isLoggedIn" class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
                <RouterLink to="/" class="text-lg font-bold text-gray-900">AI Video</RouterLink>
                <div class="flex items-center gap-4">
                    <RouterLink to="/guide" class="text-sm text-gray-500 hover:text-gray-700">操作說明</RouterLink>
                    <RouterLink to="/cases/new" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700 transition">
                        新建案
                    </RouterLink>
                    <button @click="logout" class="text-sm text-gray-500 hover:text-gray-700">登出</button>
                </div>
            </div>
        </header>
        <main class="max-w-5xl mx-auto px-4 py-6">
            <RouterView />
        </main>
    </div>
</template>
