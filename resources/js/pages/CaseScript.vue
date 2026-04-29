<script setup>
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCaseStore } from '../stores/case'
import StatusBadge from '../components/StatusBadge.vue'
import ActionButton from '../components/ActionButton.vue'

const route = useRoute()
const router = useRouter()
const store = useCaseStore()

const scriptData = computed(() => store.current?.script_v2)
const scriptShots = computed(() => scriptData.value?.shots ?? store.shots)

onMounted(() => store.load(route.params.id))

function goToImages() {
    router.push({ name: 'case.images', params: { id: route.params.id } })
}
</script>

<template>
    <div v-if="store.current">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ store.current.name }} — 腳本確認</h2>
                <p class="text-sm text-gray-500 mt-1">確認分鏡內容後，開始生成場景圖</p>
            </div>
            <StatusBadge :status="store.status" />
        </div>

        <!-- Shot 列表 -->
        <div class="space-y-3 mb-6">
            <div
                v-for="shot in scriptShots"
                :key="shot.shot_id"
                class="bg-white rounded-lg border p-4"
            >
                <div class="flex items-center justify-between mb-2">
                    <span class="font-medium text-gray-800">{{ shot.shot_id }} — {{ shot.duration_seconds }}s</span>
                    <span v-if="shot.emotion" class="text-xs text-gray-400">{{ shot.emotion }}</span>
                </div>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-gray-400 mb-1">場景</div>
                        <div class="text-gray-700">{{ shot.scene_description || '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">旁白</div>
                        <div class="text-gray-700">{{ shot.voiceover_text || '—' }}</div>
                    </div>
                </div>
                <div v-if="shot.flux_prompt" class="mt-2">
                    <div class="text-xs text-gray-400 mb-1">Flux Prompt</div>
                    <div class="text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">{{ shot.flux_prompt }}</div>
                </div>
            </div>
        </div>

        <div class="flex justify-between">
            <ActionButton variant="secondary" @click="router.push({ name: 'case.character', params: { id: route.params.id } })">
                返回角色
            </ActionButton>
            <ActionButton @click="goToImages">
                確認腳本，生成場景圖
            </ActionButton>
        </div>
    </div>
    <div v-else class="text-gray-500">載入中...</div>
</template>
