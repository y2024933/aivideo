<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import TextInput from '../components/TextInput.vue'
import InputLabel from '../components/InputLabel.vue'
import InputError from '../components/InputError.vue'
import ActionButton from '../components/ActionButton.vue'

const router = useRouter()
const auth = useAuthStore()

const email = ref('admin@aivideo.local')
const password = ref('password')
const remember = ref(false)
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
    <!-- GuestLayout 風格：置中卡片 -->
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <!-- Logo -->
        <div>
            <h1 class="text-3xl font-bold text-gray-800">AI Video</h1>
        </div>

        <!-- 登入卡片 -->
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            <form @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="email"
                        required
                        autofocus
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="error && error.includes('email') ? error : ''" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password" value="密碼" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="password"
                        required
                        autocomplete="current-password"
                    />
                </div>

                <div class="block mt-4">
                    <label class="flex items-center">
                        <input
                            type="checkbox"
                            v-model="remember"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        <span class="ms-2 text-sm text-gray-600">記住我</span>
                    </label>
                </div>

                <InputError v-if="error" class="mt-4" :message="error" />

                <div class="flex items-center justify-end mt-4">
                    <ActionButton :loading="auth.loading" class="ms-4" :class="{ 'opacity-25': auth.loading }">
                        登入
                    </ActionButton>
                </div>
            </form>
        </div>

        <!-- 提示資訊 -->
        <div class="text-center mt-4 space-y-1">
            <p class="text-xs text-gray-400">預設帳號：admin@aivideo.local / password</p>
            <RouterLink to="/guide" class="text-xs text-indigo-500 hover:underline">查看操作流程</RouterLink>
        </div>
    </div>
</template>
