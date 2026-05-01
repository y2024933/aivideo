import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useApi } from '../composables/useApi'

export const useCaseStore = defineStore('case', () => {
    const api = useApi()
    const current = ref(null)
    const loading = ref(false)
    const error = ref(null)

    const status = computed(() => current.value?.status)
    const shots = computed(() => current.value?.shots ?? [])
    const characterOptions = computed(() => current.value?.character_options ?? [])
    const voiceover = computed(() => current.value?.voiceover)

    async function load(id) {
        loading.value = true
        error.value = null
        try {
            const { data } = await api.getCase(id)
            current.value = data
        } catch (e) {
            error.value = e.response?.data?.error ?? '載入失敗'
        } finally {
            loading.value = false
        }
    }

    async function create(formData) {
        loading.value = true
        error.value = null
        try {
            const { data } = await api.createCase(formData)
            current.value = data
            return data
        } catch (e) {
            error.value = e.response?.data?.error ?? '建立失敗'
            throw e
        } finally {
            loading.value = false
        }
    }

    async function generateCharacters() {
        loading.value = true
        error.value = null
        try {
            const { data } = await api.generateCharacters(current.value.id)
            current.value = data
        } catch (e) {
            error.value = e.response?.data?.error ?? '角色生成失敗'
        } finally {
            loading.value = false
        }
    }

    async function approveCharacter(optionId) {
        loading.value = true
        try {
            const { data } = await api.approveCharacter(current.value.id, optionId)
            current.value = data
        } catch (e) {
            error.value = e.response?.data?.error ?? '核准失敗'
        } finally {
            loading.value = false
        }
    }

    async function generateScenes() {
        loading.value = true
        error.value = null
        try {
            const { data } = await api.generateScenes(current.value.id)
            current.value = data
        } catch (e) {
            error.value = e.response?.data?.error ?? '場景圖生成失敗'
        } finally {
            loading.value = false
        }
    }

    async function approveImages() {
        loading.value = true
        try {
            const { data } = await api.approveImages(current.value.id)
            current.value = data
        } catch (e) {
            error.value = e.response?.data?.error ?? '核准失敗'
        } finally {
            loading.value = false
        }
    }

    async function generateVoiceover(voiceName) {
        loading.value = true
        try {
            const { data } = await api.generateVoiceover(current.value.id, voiceName)
            current.value = { ...current.value, voiceover: data }
        } catch (e) {
            error.value = e.response?.data?.error ?? '配音生成失敗'
        } finally {
            loading.value = false
        }
    }

    async function renderVideo() {
        loading.value = true
        try {
            await api.renderVideo(current.value.id)
        } catch (e) {
            error.value = e.response?.data?.error ?? '渲染失敗'
        } finally {
            loading.value = false
        }
    }

    async function regenerateScene(shotId) {
        try {
            const { data } = await api.regenerateScene(current.value.id, shotId)
            const idx = current.value.shots.findIndex(s => s.id === shotId)
            if (idx !== -1) current.value.shots.splice(idx, 1, data)
            return data
        } catch (e) {
            error.value = e.response?.data?.error ?? '場景圖重跑失敗'
            throw e
        }
    }

    async function regenerateVideo(shotId) {
        try {
            const { data } = await api.regenerateVideo(current.value.id, shotId)
            const idx = current.value.shots.findIndex(s => s.id === shotId)
            if (idx !== -1) current.value.shots.splice(idx, 1, data)
            return data
        } catch (e) {
            error.value = e.response?.data?.error ?? '動畫重跑失敗'
            throw e
        }
    }

    async function updateVideoSettings(settings) {
        loading.value = true
        error.value = null
        try {
            const { data } = await api.updateVideoSettings(current.value.id, settings)
            current.value = { ...current.value, ...data }
        } catch (e) {
            error.value = e.response?.data?.error ?? '設定儲存失敗'
            throw e
        } finally {
            loading.value = false
        }
    }

    function refresh() {
        if (current.value?.id) load(current.value.id)
    }

    return {
        current, loading, error,
        status, shots, characterOptions, voiceover,
        load, create, generateCharacters, approveCharacter,
        generateScenes, approveImages, generateVoiceover,
        renderVideo, regenerateScene, regenerateVideo, updateVideoSettings, refresh,
    }
})
