import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null)
    const loading = ref(false)

    const isLoggedIn = computed(() => !!user.value)

    async function fetchUser() {
        try {
            const { data } = await axios.get('/api/user')
            user.value = data
        } catch {
            user.value = null
        }
    }

    async function login(email, password) {
        loading.value = true
        try {
            await axios.get('/sanctum/csrf-cookie')
            const { data } = await axios.post('/api/login', { email, password })
            user.value = data
            return true
        } catch (e) {
            throw e.response?.data?.error ?? '登入失敗'
        } finally {
            loading.value = false
        }
    }

    async function logout() {
        await axios.post('/api/logout')
        user.value = null
    }

    return { user, loading, isLoggedIn, fetchUser, login, logout }
})
