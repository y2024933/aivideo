<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import StatusBadge from '../components/StatusBadge.vue'
import axios from 'axios'

const router = useRouter()
const cases = ref([])
const loading = ref(true)

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/cases')
        cases.value = data
    } catch (e) {
        // 尚未有 list endpoint，暫時空陣列
    } finally {
        loading.value = false
    }
})

function nextStep(c) {
    const routes = {
        draft: 'case.character',
        character_generating: 'case.character',
        character_pending_review: 'case.character',
        character_failed: 'case.character',
        character_approved: 'case.script',
        script_pending_review: 'case.script',
        images_generating: 'case.images',
        images_partial: 'case.images',
        images_pending_review: 'case.images',
        images_approved: 'case.final',
        producing_final: 'case.final',
        final_pending_review: 'case.final',
        completed: 'case.final',
    }
    router.push({ name: routes[c.status] ?? 'case.character', params: { id: c.id } })
}
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-gray-900 mb-4">建案列表</h2>

        <div v-if="loading" class="text-gray-500">載入中...</div>

        <div v-else-if="cases.length === 0" class="text-center py-16 text-gray-400">
            <p class="text-lg mb-2">尚無建案</p>
            <RouterLink to="/cases/new" class="text-indigo-600 hover:underline">建立第一個建案</RouterLink>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="c in cases"
                :key="c.id"
                class="bg-white rounded-lg border p-4 flex items-center justify-between cursor-pointer hover:border-indigo-300 transition"
                @click="nextStep(c)"
            >
                <div>
                    <div class="font-medium text-gray-900">{{ c.name }}</div>
                    <div class="text-sm text-gray-500 mt-0.5">{{ c.builder_name }} / {{ c.location }}</div>
                </div>
                <StatusBadge :status="c.status" />
            </div>
        </div>
    </div>
</template>
