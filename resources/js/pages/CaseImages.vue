<script setup>
import { onMounted, computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCaseStore } from '../stores/case'
import StatusBadge from '../components/StatusBadge.vue'
import ActionButton from '../components/ActionButton.vue'

const route = useRoute()
const router = useRouter()
const store = useCaseStore()

const isGenerating = computed(() => store.status === 'images_generating')
const isPending = computed(() => ['images_pending_review', 'images_partial'].includes(store.status))
const canGenerate = computed(() => ['character_approved', 'script_pending_review', 'images_partial'].includes(store.status))
const canApprove = computed(() => store.status === 'images_pending_review')
const allDone = computed(() => store.shots.every(s => s.image_status === 'done'))
const failedShots = computed(() => store.shots.filter(s => s.image_status === 'failed'))

onMounted(() => store.load(route.params.id))

async function generate() {
    await store.generateScenes()
    pollUntilDone()
}

async function approve() {
    await store.approveImages()
    router.push({ name: 'case.final', params: { id: route.params.id } })
}

let pollTimer = null
function pollUntilDone() {
    clearInterval(pollTimer)
    pollTimer = setInterval(async () => {
        await store.refresh()
        if (!['images_generating'].includes(store.status)) clearInterval(pollTimer)
    }, 5000)
}

const regeneratingScenes = ref(new Set())

async function regenerateScene(shotId) {
    regeneratingScenes.value.add(shotId)
    try {
        await store.regenerateScene(shotId)
    } finally {
        regeneratingScenes.value.delete(shotId)
    }
}

function statusColor(status) {
    return {
        pending: 'bg-gray-400',
        processing: 'bg-yellow-400 animate-pulse',
        done: 'bg-green-400',
        failed: 'bg-red-400',
    }[status] ?? 'bg-gray-400'
}
</script>

<template>
    <div>
        <!-- 頁面標題 -->
        <header class="bg-white shadow -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 mb-6 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ store.current?.name ?? '載入中...' }} — 場景圖
                </h2>
                <StatusBadge v-if="store.current" :status="store.status" />
            </div>
        </header>

        <div v-if="store.current">
            <p class="text-sm text-gray-500 mb-6">審核 {{ store.shots.length }} 張場景圖</p>

            <!-- 3x3 Grid -->
            <div v-if="store.shots.length" class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
                <div
                    v-for="shot in store.shots"
                    :key="shot.id"
                    class="bg-white rounded-lg border overflow-hidden"
                >
                    <div class="relative">
                        <img
                            v-if="shot.image_url"
                            :src="shot.image_url"
                            :alt="shot.shot_id"
                            class="w-full aspect-[9/16] object-cover"
                        />
                        <div v-else class="w-full aspect-[9/16] bg-gray-100 flex items-center justify-center text-gray-400">
                            {{ shot.image_status === 'processing' ? '生成中...' : '待生成' }}
                        </div>
                        <!-- 狀態指示燈 -->
                        <div class="absolute top-2 right-2 flex items-center gap-1">
                            <span :class="['w-2 h-2 rounded-full', statusColor(shot.image_status)]" />
                        </div>
                    </div>
                    <div class="p-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-700">{{ shot.shot_id }} ({{ shot.duration_seconds }}s)</span>
                            <span v-if="shot.image_status === 'failed'" class="text-xs text-red-500">失敗</span>
                        </div>
                        <p v-if="shot.subtitle" class="text-xs text-gray-400 mt-1 truncate">{{ shot.subtitle }}</p>
                        <button
                            v-if="shot.image_status !== 'processing'"
                            :disabled="regeneratingScenes.has(shot.id)"
                            class="mt-1 w-full text-xs py-1 rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="regenerateScene(shot.id)"
                        >
                            {{ regeneratingScenes.has(shot.id) ? '生成中...' : (shot.image_url ? '重跑' : '生成') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- 失敗的 shots 提示 -->
            <div v-if="failedShots.length" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <p class="text-red-700 text-sm">
                    {{ failedShots.length }} 張場景圖生成失敗：{{ failedShots.map(s => s.shot_id).join('、') }}
                </p>
                <ActionButton variant="danger" :loading="store.loading" @click="generate" class="mt-2">
                    重新生成失敗的鏡頭
                </ActionButton>
            </div>

            <!-- 操作按鈕 -->
            <div class="flex justify-between">
                <ActionButton variant="secondary" @click="router.push({ name: 'case.script', params: { id: route.params.id } })">
                    返回腳本
                </ActionButton>
                <div class="flex gap-3">
                    <ActionButton v-if="canGenerate" variant="secondary" :loading="store.loading" @click="generate">
                        全部重新生成
                    </ActionButton>
                    <ActionButton v-if="canApprove && allDone" variant="success" :loading="store.loading" @click="approve">
                        全部核准，開始生成動畫
                    </ActionButton>
                </div>
            </div>

            <p v-if="store.error" class="text-red-600 text-sm mt-4">{{ store.error }}</p>
        </div>
        <div v-else class="text-gray-500">載入中...</div>
    </div>
</template>
