<script setup>
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCaseStore } from '../stores/case'
import StatusBadge from '../components/StatusBadge.vue'
import ActionButton from '../components/ActionButton.vue'

const route = useRoute()
const router = useRouter()
const store = useCaseStore()

const isGenerating = computed(() => store.status === 'character_generating')
const isPendingReview = computed(() => store.status === 'character_pending_review')
const isFailed = computed(() => store.status === 'character_failed')
const canGenerate = computed(() => ['draft', 'character_failed'].includes(store.status))
const canProceed = computed(() => store.status === 'character_approved')

onMounted(() => store.load(route.params.id))

async function generate() {
    await store.generateCharacters()
}

async function approve(optionId) {
    await store.approveCharacter(optionId)
}

function next() {
    router.push({ name: 'case.script', params: { id: route.params.id } })
}
</script>

<template>
    <div v-if="store.current">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ store.current.name }} — 角色預覽</h2>
                <p class="text-sm text-gray-500 mt-1">選擇一個角色作為全片主角</p>
            </div>
            <StatusBadge :status="store.status" />
        </div>

        <!-- 生成按鈕 -->
        <div v-if="canGenerate" class="text-center py-12">
            <p class="text-gray-500 mb-4">點擊生成 4 張角色預覽圖</p>
            <ActionButton :loading="store.loading" @click="generate">生成角色預覽</ActionButton>
        </div>

        <!-- 生成中 -->
        <div v-else-if="isGenerating" class="text-center py-12">
            <div class="animate-pulse text-gray-500">角色生成中，請稍候...</div>
        </div>

        <!-- 角色選擇 -->
        <div v-else-if="isPendingReview || canProceed" class="grid grid-cols-2 gap-4">
            <div
                v-for="opt in store.characterOptions"
                :key="opt.id"
                :class="[
                    'rounded-lg border-2 overflow-hidden transition cursor-pointer',
                    store.current.approved_character_id === opt.id
                        ? 'border-green-500 ring-2 ring-green-200'
                        : 'border-gray-200 hover:border-indigo-300'
                ]"
            >
                <img
                    :src="opt.image_url"
                    :alt="`角色選項`"
                    class="w-full aspect-[9/16] object-cover bg-gray-100"
                />
                <div class="p-3 flex items-center justify-between">
                    <span v-if="store.current.approved_character_id === opt.id" class="text-green-600 text-sm font-medium">已核准</span>
                    <span v-else class="text-gray-400 text-sm">未選擇</span>
                    <ActionButton
                        v-if="isPendingReview && store.current.approved_character_id !== opt.id"
                        variant="success"
                        :loading="store.loading"
                        @click="approve(opt.id)"
                    >
                        選這個
                    </ActionButton>
                </div>
            </div>
        </div>

        <!-- 失敗 -->
        <div v-else-if="isFailed" class="text-center py-12">
            <p class="text-red-600 mb-4">角色生成失敗</p>
            <ActionButton @click="generate">重新生成</ActionButton>
        </div>

        <!-- 下一步 -->
        <div v-if="canProceed" class="mt-6 flex justify-between">
            <ActionButton variant="secondary" @click="generate">重新生成角色</ActionButton>
            <ActionButton @click="next">下一步：腳本確認</ActionButton>
        </div>

        <p v-if="store.error" class="text-red-600 text-sm mt-4">{{ store.error }}</p>
    </div>
    <div v-else class="text-gray-500">載入中...</div>
</template>
