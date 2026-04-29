<script setup>
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCaseStore } from '../stores/case'
import StatusBadge from '../components/StatusBadge.vue'
import ActionButton from '../components/ActionButton.vue'

const route = useRoute()
const router = useRouter()
const store = useCaseStore()

onMounted(() => store.load(route.params.id))

const nextStepRoute = computed(() => {
    const map = {
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
    return map[store.status] ?? 'case.character'
})

const nextStepLabel = computed(() => {
    const map = {
        draft: '開始生成角色',
        character_generating: '查看角色生成進度',
        character_pending_review: '選擇角色',
        character_failed: '重新生成角色',
        character_approved: '確認腳本',
        script_pending_review: '確認腳本',
        images_generating: '查看場景圖進度',
        images_partial: '處理失敗的場景圖',
        images_pending_review: '審核場景圖',
        images_approved: '查看動畫進度',
        producing_final: '查看製作進度',
        final_pending_review: '審核成品',
        completed: '查看成品',
    }
    return map[store.status] ?? '繼續'
})
</script>

<template>
    <div>
        <header class="bg-white shadow -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 mb-6 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ store.current?.name ?? '載入中...' }}
                </h2>
                <StatusBadge v-if="store.current" :status="store.status" />
            </div>
        </header>

        <div v-if="store.current" class="space-y-6">
            <!-- 下一步操作 -->
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-5 flex items-center justify-between">
                <div>
                    <p class="font-medium text-indigo-800">下一步</p>
                    <p class="text-sm text-indigo-600">{{ nextStepLabel }}</p>
                </div>
                <ActionButton @click="router.push({ name: nextStepRoute, params: { id: route.params.id } })">
                    {{ nextStepLabel }}
                </ActionButton>
            </div>

            <!-- 基本資料 -->
            <section class="bg-white rounded-lg border p-6">
                <h3 class="font-semibold text-gray-800 mb-4">建案資料</h3>
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-400">建商</dt>
                        <dd class="text-gray-800 mt-0.5">{{ store.current.builder_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">地點</dt>
                        <dd class="text-gray-800 mt-0.5">{{ store.current.location || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">坪數</dt>
                        <dd class="text-gray-800 mt-0.5">{{ store.current.area_range || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">客群</dt>
                        <dd class="text-gray-800 mt-0.5">{{ store.current.target_audience || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">調性</dt>
                        <dd class="text-gray-800 mt-0.5">{{ store.current.tone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">累計費用</dt>
                        <dd class="text-gray-800 mt-0.5">${{ Number(store.current.cost_usd).toFixed(2) }}</dd>
                    </div>
                </dl>
            </section>

            <!-- 角色 DNA -->
            <section v-if="store.current.character_dna" class="bg-white rounded-lg border p-6">
                <h3 class="font-semibold text-gray-800 mb-2">
                    角色 DNA
                    <span v-if="store.current.character_nickname" class="text-gray-400 font-normal">（{{ store.current.character_nickname }}）</span>
                </h3>
                <p class="text-sm text-gray-600 font-mono bg-gray-50 p-3 rounded whitespace-pre-wrap">{{ store.current.character_dna }}</p>
            </section>

            <!-- Shots 列表 -->
            <section v-if="store.shots.length" class="bg-white rounded-lg border p-6">
                <h3 class="font-semibold text-gray-800 mb-4">分鏡腳本（{{ store.shots.length }} 個鏡頭）</h3>
                <div class="space-y-3">
                    <div
                        v-for="shot in store.shots"
                        :key="shot.id"
                        class="border rounded-lg p-4"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-xs font-bold">{{ shot.shot_id }}</span>
                                <span class="text-sm text-gray-500">{{ shot.duration_seconds }}s</span>
                                <span v-if="shot.emotion" class="text-xs text-gray-400">{{ shot.emotion }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="shot.image_url" class="w-2 h-2 rounded-full bg-green-400" title="圖片完成" />
                                <span v-if="shot.video_url" class="w-2 h-2 rounded-full bg-blue-400" title="影片完成" />
                            </div>
                        </div>

                        <p v-if="shot.scene_description" class="text-sm text-gray-700 mb-2">{{ shot.scene_description }}</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div v-if="shot.voiceover_text">
                                <span class="text-xs text-gray-400">旁白</span>
                                <p class="text-gray-600">{{ shot.voiceover_text }}</p>
                            </div>
                            <div v-if="shot.subtitle">
                                <span class="text-xs text-gray-400">字幕</span>
                                <p class="text-gray-600">{{ shot.subtitle }}</p>
                            </div>
                        </div>

                        <details v-if="shot.flux_prompt" class="mt-2">
                            <summary class="text-xs text-indigo-500 cursor-pointer hover:underline">Flux Prompt</summary>
                            <p class="text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded mt-1">{{ shot.flux_prompt }}</p>
                        </details>
                        <details v-if="shot.kling_prompt" class="mt-1">
                            <summary class="text-xs text-indigo-500 cursor-pointer hover:underline">Kling Prompt</summary>
                            <p class="text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded mt-1">{{ shot.kling_prompt }}</p>
                        </details>
                    </div>
                </div>
            </section>

            <!-- 故事設定 -->
            <section v-if="store.current.story_outline" class="bg-white rounded-lg border p-6">
                <h3 class="font-semibold text-gray-800 mb-3">故事設定</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-gray-400">故事大綱</span>
                        <p class="text-gray-700 mt-0.5">{{ store.current.story_outline }}</p>
                    </div>
                    <div v-if="store.current.must_have" class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-gray-400">必要元素</span>
                            <p class="text-gray-700 mt-0.5">{{ store.current.must_have }}</p>
                        </div>
                        <div v-if="store.current.taboos">
                            <span class="text-gray-400">禁忌</span>
                            <p class="text-gray-700 mt-0.5">{{ store.current.taboos }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div v-else class="text-gray-500">載入中...</div>
    </div>
</template>
