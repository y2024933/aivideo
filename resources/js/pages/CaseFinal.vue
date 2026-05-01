<script setup>
import { onMounted, computed, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useCaseStore } from '../stores/case'
import StatusBadge from '../components/StatusBadge.vue'
import ActionButton from '../components/ActionButton.vue'
import SubtitleSettings from '../components/SubtitleSettings.vue'
import TransitionSettings from '../components/TransitionSettings.vue'
import RemotionPreview from '../components/RemotionPreview.vue'

const route = useRoute()
const store = useCaseStore()

const regeneratingVideos = ref(new Set())
const savingSettings = ref(false)
const settingsSaved = ref(false)

const subtitleSettings = ref({
    fontSize: 'medium',
    color: '#ffffff',
    position: 'bottom',
    animation: 'slideIn',
})

const transitionSettings = ref({
    global: 'crossfade',
    perShot: {},
})

async function regenerateVideo(shotId) {
    regeneratingVideos.value.add(shotId)
    try {
        await store.regenerateVideo(shotId)
    } finally {
        regeneratingVideos.value.delete(shotId)
    }
}

const isProducing = computed(() => store.status === 'producing_final')
const isFinalReview = computed(() => store.status === 'final_pending_review')
const isCompleted = computed(() => store.status === 'completed')

const videosReady = computed(() => store.shots.every(s => s.video_status === 'done'))
const videosPending = computed(() => store.shots.filter(s => s.video_status === 'processing'))
const videosFailed = computed(() => store.shots.filter(s => s.video_status === 'failed'))
const hasVoiceover = computed(() => !!store.voiceover?.audio_url)
const finalVideoUrl = computed(() => store.current?.final_video_url)

// 輪詢動畫進度
const voices = [
    { id: 'zh-TW-HsiaoChenNeural', label: '曉臻（女，溫暖）' },
    { id: 'zh-TW-HsiaoYuNeural', label: '曉雨（女，清亮）' },
    { id: 'zh-TW-YunJheNeural', label: '雲哲（男）' },
]
const selectedVoice = ref('zh-TW-HsiaoChenNeural')

let pollTimer = null
function startPolling() {
    pollTimer = setInterval(() => store.refresh(), 5000)
}
function stopPolling() {
    if (pollTimer) clearInterval(pollTimer)
}

onMounted(async () => {
    await store.load(route.params.id)
    if (store.voiceover?.voice_id) selectedVoice.value = store.voiceover.voice_id
    // 載入已存設定
    if (store.current?.subtitle_settings) subtitleSettings.value = { ...subtitleSettings.value, ...store.current.subtitle_settings }
    if (store.current?.global_transition) transitionSettings.value.global = store.current.global_transition
    const shotTransitions = {}
    store.shots.forEach(s => { if (s.transition) shotTransitions[s.id] = s.transition })
    if (Object.keys(shotTransitions).length) transitionSettings.value.perShot = shotTransitions
    startPolling()
})
onUnmounted(stopPolling)

async function genVoiceover() {
    await store.generateVoiceover(selectedVoice.value)
}

async function render() {
    await store.renderVideo()
}

async function saveVideoSettings() {
    savingSettings.value = true
    settingsSaved.value = false
    try {
        await store.updateVideoSettings({
            subtitle_settings: subtitleSettings.value,
            global_transition: transitionSettings.value.global,
            shots_transitions: transitionSettings.value.perShot,
        })
        settingsSaved.value = true
        setTimeout(() => { settingsSaved.value = false }, 3000)
    } finally {
        savingSettings.value = false
    }
}

// Remotion 預覽相關計算
const previewFps = 30
const overlapFrames = 15 // 0.5s transition overlap

const remotionInputProps = computed(() => ({
    shots: store.shots.map((shot, i) => ({
        videoUrl: shot.video_url || '',
        subtitle: shot.subtitle || '',
        durationSec: shot.duration_seconds || 5,
        isPublicFacility: shot.is_public_facility || false,
        voiceoverUrl: shot.voiceover_url || '',
        transition: transitionSettings.value.perShot?.[shot.id] || transitionSettings.value.global || 'crossfade',
    })),
    subtitleSettings: subtitleSettings.value,
    globalTransition: transitionSettings.value.global || 'crossfade',
    watermark: store.current?.compliance_watermark ? { text: store.current.compliance_watermark } : null,
    publicFacilityLabel: '公設示意圖',
}))

const previewDurationInFrames = computed(() => {
    const shots = remotionInputProps.value.shots
    if (!shots.length) return 1
    const totalFrames = shots.reduce((sum, s) => sum + Math.round((s.durationSec || 5) * previewFps), 0)
    const overlaps = Math.max(0, shots.length - 1) * overlapFrames
    return Math.max(1, totalFrames - overlaps)
})

const canPreview = computed(() => store.shots.some(s => s.video_url && s.video_status === 'done'))

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
                        <div class="p-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium">{{ shot.shot_id }}</span>
                                <span v-if="shot.video_status === 'failed'" class="text-red-500">{{ shot.video_error || '失敗' }}</span>
                            </div>
                            <button
                                v-if="shot.video_status === 'done' || shot.video_status === 'failed'"
                                :disabled="regeneratingVideos.has(shot.id)"
                                class="mt-1 w-full text-xs py-1 rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="regenerateVideo(shot.id)"
                            >
                                {{ regeneratingVideos.has(shot.id) ? '重跑中...' : '重跑' }}
                            </button>
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
                    <div class="flex items-center gap-2 mt-2">
                        <select v-model="selectedVoice" class="text-sm border-gray-300 rounded-md">
                            <option v-for="v in voices" :key="v.id" :value="v.id">{{ v.label }}</option>
                        </select>
                        <ActionButton variant="secondary" :loading="store.loading" @click="genVoiceover">
                            重新生成
                        </ActionButton>
                    </div>
                </div>
                <div v-else>
                    <div class="flex items-center gap-3 mb-3">
                        <label class="text-sm text-gray-600">選擇聲音：</label>
                        <select v-model="selectedVoice" class="text-sm border-gray-300 rounded-md">
                            <option v-for="v in voices" :key="v.id" :value="v.id">{{ v.label }}</option>
                        </select>
                    </div>
                    <ActionButton :loading="store.loading" @click="genVoiceover" :disabled="!videosReady">
                        生成配音
                    </ActionButton>
                    <p v-if="!videosReady" class="text-xs text-gray-400 mt-1">等待所有動畫完成後才能生成配音</p>
                </div>
            </section>

            <!-- 字幕與轉場設定 -->
            <section class="mb-4 space-y-4">
                <SubtitleSettings v-model="subtitleSettings" />
                <TransitionSettings v-model="transitionSettings" :shots="store.shots" />
                <div class="flex items-center gap-3">
                    <ActionButton :loading="savingSettings" @click="saveVideoSettings">
                        儲存設定
                    </ActionButton>
                    <span v-if="settingsSaved" class="text-sm text-green-600">已儲存</span>
                </div>
            </section>

            <!-- 即時預覽 -->
            <section v-if="canPreview" class="bg-white rounded-lg border p-6 mb-4">
                <h3 class="font-semibold text-gray-800 mb-3">即時預覽</h3>
                <p class="text-xs text-gray-400 mb-3">調整字幕/轉場設定後可即時預覽效果</p>
                <RemotionPreview
                    :input-props="remotionInputProps"
                    :duration-in-frames="previewDurationInFrames"
                    :fps="previewFps"
                />
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
