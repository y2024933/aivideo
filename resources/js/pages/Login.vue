<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import ActionButton from '../components/ActionButton.vue'

const router = useRouter()
const auth = useAuthStore()

const email = ref('admin@aivideo.local')
const password = ref('password')
const error = ref('')

async function submit() {
    error.value = ''
    try {
        await auth.login(email.value, password.value)
        router.push('/')
    } catch (e) {
        error.value = e
    }
}
</script>

<template>
    <div class="min-h-[70vh] flex items-center justify-center">
        <div class="w-full max-w-sm">
            <h1 class="text-2xl font-bold text-gray-900 text-center mb-8">AI Video</h1>

            <form @submit.prevent="submit" class="bg-white rounded-lg border p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input v-model="email" type="email" required autofocus class="w-full rounded-lg border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">密碼</label>
                    <input v-model="password" type="password" required class="w-full rounded-lg border-gray-300 text-sm" />
                </div>

                <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>

                <ActionButton :loading="auth.loading" class="w-full justify-center">登入</ActionButton>
            </form>

            <p class="text-center text-xs text-gray-400 mt-4">預設帳號：admin@aivideo.local / password</p>
        </div>
    </div>
</template>
