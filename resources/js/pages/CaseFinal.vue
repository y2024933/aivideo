<script setup>
import { onMounted, computed, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useCaseStore } from '../stores/case'
import StatusBadge from '../components/StatusBadge.vue'
import ActionButton from '../components/ActionButton.vue'

const route = useRoute()
const store = useCaseStore()

const isProducing = computed(() => store.status === 'producing_final')
const isFinalReview = computed(() => store.status === 'final_pending_review')
const isCompleted = computed(() => store.status === 'completed')

const videosReady = computed(() => store.shots.every(s => s.video_status === 'done'))
const videosPending = computed(() => store.shots.filter(s => s.video_status === 'processing'))
const videosFailed = computed(() => store.shots.filter(s => s.video_status === 'failed'))
const hasVoiceover = computed(() => !!store.voiceover?.audio_url)
const finalVideoUrl = computed(() => store.current?.final_video_url)

// 輪詢動畫進度
let pollTimer = null
function startPolling() {
    pollTimer = setInterval(() => store.refresh(), 5000)
}
function stopPolling() {
    if (pollTimer) clearInterval(pollTimer)
}

onMounted(() => {
    store.load(route.params.id)
    startPolling()
})
onUnmounted(stopPolling)

async function genVoiceover() {
    await store.generateVoiceover()
}

async function render() {
    await store.renderVideo()
}

function statusIcon(status) {
    return { pending: '...', processing: '~', done: 'v', failed: 'x' }[status] ?? '...'
}

function statusIconClass(status) {
    return {
        pending: 'text-gray-400',
        processing: 'text-yellow-500 animate-pulse',
        done: 'text-green-500',
        failed: 'text-red-500',
    }[status] ?? 'text-gray-400'
}
</script>

<template>
    <div>
        <!-- 頁面標題 -->
        <header class="bg-white shadow -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 mb-6 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ store.current?.name ?? '載入中...' }} — 最終審核
                </h2>
                <StatusBadge v-if="store.current" :status="store.status" />
            </div>
        </header>

        <div v-if="store.current">
            <p class="text-sm text-gray-500 mb-6">
                費用：${{ Number(store.current.cost_usd).toFixed(2) }} USD
            </p>

            <!-- 動畫進度 -->
            <section class="bg-white rounded-lg border p-6 mb-4">
                <h3 class="font-semibold text-gray-800 mb-3">動畫片段</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div v-for="shot in store.shots" :key="shot.id" class="border rounded-lg overflow-hidden">
                        <div v-if="shot.video_url && shot.video_status === 'done'" class="relative">
                            <video :src="shot.video_url" class="w-full aspect-[9/16] object-cover" controls preload="metadata" />
                        </div>
                        <div v-else class="w-full aspect-[9/16] bg-gray-50 flex flex-col items-center justify-center text-sm">
                            <span :class="['text-2xl mb-1', statusIconClass(shot.video_status)]">{{ statusIcon(shot.video_status) }}</span>
                            <span class="text-gray-400">{{ shot.video_status === 'processing' ? '生成中' : shot.video_status }}</span>
                        </div>
                        <div class="p-2 flex items-center justify-between text-xs">
                            <span class="font-medium">{{ shot.shot_id }}</span>
                            <span v-if="shot.video_status === 'failed'" class="text-red-500">{{ shot.video_error || '失敗' }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="videosPending.length" class="mt-3 text-sm text-yellow-600">
                    {{ videosPending.length }} 段影片生成中...
                </div>
                <div v-if="videosFailed.length" class="mt-3 text-sm text-red-600">
                    {{ videosFailed.length }} 段影片失敗：{{ videosFailed.map(s => s.shot_id).join('、') }}
                </div>
            </section>

            <!-- 配音 -->
            <section class="bg-white rounded-lg border p-6 mb-4">
                <h3 class="font-semibold text-gray-800 mb-3">配音</h3>
                <div v-if="hasVoiceover" class="space-y-2">
                    <audio :src="store.voiceover.audio_url" controls class="w-full" />
                    <p class="text-sm text-gray-500">
                        時長：{{ store.voiceover.duration_seconds }}s / 聲音：{{ store.voiceover.voice_id }}
                    </p>
                </div>
                <div v-else>
                    <ActionButton :loading="store.loading" @click="genVoiceover" :disabled="!videosReady">
                        生成配音
                    </ActionButton>
                    <p v-if="!videosReady" class="text-xs text-gray-400 mt-1">等待所有動畫完成後才能生成配音</p>
                </div>
            </section>

            <!-- 渲染最終影片 -->
            <section class="bg-white rounded-lg border p-6 mb-4">
                <h3 class="font-semibold text-gray-800 mb-3">最終影片</h3>

                <div v-if="finalVideoUrl">
                    <video :src="finalVideoUrl" controls class="w-full max-w-md mx-auto rounded-lg" />
                    <div class="flex justify-center mt-4">
                        <a :href="finalVideoUrl" download class="text-indigo-600 hover:underline text-sm">下載影片</a>
                    </div>
                </div>

                <div v-else-if="isProducing" class="text-center py-8">
                    <div class="animate-pulse text-gray-500">影片渲染中，請稍候...</div>
                </div>

                <div v-else>
                    <ActionButton
                        :loading="store.loading"
                        :disabled="!videosReady || !hasVoiceover"
                        @click="render"
                    >
                        渲染最終影片
                    </ActionButton>
                    <p v-if="!videosReady || !hasVoiceover" class="text-xs text-gray-400 mt-1">
                        需要所有動畫 + 配音都完成
                    </p>
                </div>
            </section>

            <!-- 完成 -->
            <div v-if="isCompleted || isFinalReview" class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                <p class="text-green-800 font-semibold text-lg mb-2">影片製作完成</p>
                <p class="text-green-600 text-sm">總費用：${{ Number(store.current.cost_usd).toFixed(2) }} USD</p>
            </div>

            <p v-if="store.error" class="text-red-600 text-sm mt-4">{{ store.error }}</p>
        </div>
        <div v-else class="text-gray-500">載入中...</div>
    </div>
</template>
