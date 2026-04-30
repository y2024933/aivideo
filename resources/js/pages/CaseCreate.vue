<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useCaseStore } from '../stores/case'
import { parseScriptMarkdown } from '../composables/useMarkdownParser'
import ActionButton from '../components/ActionButton.vue'
import InputLabel from '../components/InputLabel.vue'
import TextInput from '../components/TextInput.vue'

const router = useRouter()
const store = useCaseStore()

const form = reactive({
    name: '',
    builder_name: '',
    location: '',
    area_range: '',
    price_range: '',
    target_audience: 'first_buyer',
    tone: 'warm_family',
    character_dna: '',
    character_nickname: '',
    story_outline: '',
    must_have: '',
    taboos: '',
    video_length_seconds: 60,
})

const shots = ref([
    { shot_id: 'S01', shot_order: 1, duration_seconds: 4, scene_description: '', voiceover_text: '', subtitle: '', emotion: '', flux_prompt: '', kling_prompt: '' },
    { shot_id: 'S02', shot_order: 2, duration_seconds: 4, scene_description: '', voiceover_text: '', subtitle: '', emotion: '', flux_prompt: '', kling_prompt: '' },
    { shot_id: 'S03', shot_order: 3, duration_seconds: 4, scene_description: '', voiceover_text: '', subtitle: '', emotion: '', flux_prompt: '', kling_prompt: '' },
    { shot_id: 'S04', shot_order: 4, duration_seconds: 5, scene_description: '', voiceover_text: '', subtitle: '', emotion: '', flux_prompt: '', kling_prompt: '' },
    { shot_id: 'S05', shot_order: 5, duration_seconds: 5, scene_description: '', voiceover_text: '', subtitle: '', emotion: '', flux_prompt: '', kling_prompt: '' },
    { shot_id: 'S06', shot_order: 6, duration_seconds: 5, scene_description: '', voiceover_text: '', subtitle: '', emotion: '', flux_prompt: '', kling_prompt: '' },
    { shot_id: 'S07', shot_order: 7, duration_seconds: 8, scene_description: '', voiceover_text: '', subtitle: '', emotion: '', flux_prompt: '', kling_prompt: '' },
    { shot_id: 'S08', shot_order: 8, duration_seconds: 5, scene_description: '', voiceover_text: '', subtitle: '', emotion: '', flux_prompt: '', kling_prompt: '' },
    { shot_id: 'S09', shot_order: 9, duration_seconds: 5, scene_description: '', voiceover_text: '', subtitle: '', emotion: '', flux_prompt: '', kling_prompt: '' },
])

const submitting = ref(false)
const activeShot = ref(0)

function addShot() {
    const n = shots.value.length + 1
    shots.value.push({
        shot_id: `S${String(n).padStart(2, '0')}`,
        shot_order: n,
        duration_seconds: 5,
        scene_description: '', voiceover_text: '', subtitle: '', emotion: '',
        flux_prompt: '', kling_prompt: '',
    })
}

function removeShot(i) {
    if (shots.value.length <= 1) return
    shots.value.splice(i, 1)
    shots.value.forEach((s, idx) => {
        s.shot_order = idx + 1
        s.shot_id = `S${String(idx + 1).padStart(2, '0')}`
    })
    if (activeShot.value >= shots.value.length) activeShot.value = shots.value.length - 1
}

async function submit() {
    submitting.value = true
    try {
        const caseData = await store.create({
            ...form,
            script_v2: { shots: shots.value },
        })
        router.push({ name: 'case.character', params: { id: caseData.id } })
    } catch (e) {
        // error 已在 store 處理
    } finally {
        submitting.value = false
    }
}

/* 匯入 Markdown 腳本 */
const importExpanded = ref(false)
const importText = ref('')
const importError = ref('')

function importMarkdown() {
    importError.value = ''
    if (!importText.value.trim()) {
        importError.value = '請貼上 Markdown 腳本內容'
        return
    }
    try {
        const result = parseScriptMarkdown(importText.value)
        if (!result.shots.length) {
            importError.value = '未偵測到任何鏡頭資料，請確認格式是否正確'
            return
        }
        // 回填 form
        if (result.name) form.name = result.name
        if (result.builder_name) form.builder_name = result.builder_name
        if (result.character_nickname) form.character_nickname = result.character_nickname
        if (result.character_dna) form.character_dna = result.character_dna
        // 回填 shots
        shots.value = result.shots
        activeShot.value = 0
        importExpanded.value = false
    } catch (e) {
        importError.value = `解析失敗：${e.message}`
    }
}

/* 統一 input 樣式（給原生 input / textarea / select 使用） */
const inputClass = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm'
</script>

<template>
    <div>
        <!-- 頁面標題 -->
        <header class="bg-white shadow -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 mb-6 px-4 sm:px-6 lg:px-8 py-6">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">建立新建案</h2>
        </header>

        <div class="max-w-3xl mx-auto">
            <form @submit.prevent="submit" class="space-y-8">
                <!-- 匯入 Markdown 腳本 -->
                <section class="rounded-lg border border-blue-200 bg-blue-50">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-6 py-4 text-left"
                        @click="importExpanded = !importExpanded"
                    >
                        <span class="font-semibold text-blue-700">從 Markdown 腳本匯入</span>
                        <svg :class="['w-5 h-5 text-blue-500 transition-transform', importExpanded ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="importExpanded" class="px-6 pb-5 space-y-3">
                        <p class="text-xs text-blue-600">貼上完整的 Markdown 腳本（含角色 DNA、鏡頭時長表、Flux/Kling Prompt、配音稿、字幕），解析後會覆蓋目前所有欄位。</p>
                        <textarea
                            v-model="importText"
                            rows="10"
                            class="w-full font-mono text-xs border-blue-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm"
                            placeholder="# 建案名稱｜60 秒影片腳本 v2&#10;&#10;> **建案**：XXX ｜ **建商**：YYY&#10;> **主角**：角色暱稱&#10;&#10;## 角色 DNA&#10;```&#10;...&#10;```&#10;..."
                        />
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition"
                                @click="importMarkdown"
                            >
                                解析並填入
                            </button>
                            <p v-if="importError" class="text-red-600 text-sm">{{ importError }}</p>
                        </div>
                    </div>
                </section>

                <!-- 建案基本資料 -->
                <section class="bg-white rounded-lg border p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">建案資料</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="建案名稱 *" />
                            <TextInput v-model="form.name" required class="mt-1 block w-full" placeholder="例：松韻苑" />
                        </div>
                        <div>
                            <InputLabel value="建商名稱" />
                            <TextInput v-model="form.builder_name" class="mt-1 block w-full" placeholder="例：陽光建設" />
                        </div>
                        <div>
                            <InputLabel value="地點" />
                            <TextInput v-model="form.location" class="mt-1 block w-full" placeholder="例：台中市北屯區" />
                        </div>
                        <div>
                            <InputLabel value="坪數範圍" />
                            <TextInput v-model="form.area_range" class="mt-1 block w-full" placeholder="例：28-42 坪" />
                        </div>
                        <div>
                            <InputLabel value="目標客群" />
                            <select v-model="form.target_audience" :class="['mt-1', inputClass]">
                                <option value="first_buyer">首購族</option>
                                <option value="upgrade">換屋族</option>
                                <option value="retiree">退休族</option>
                                <option value="investor">投資客</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="調性" />
                            <select v-model="form.tone" :class="['mt-1', inputClass]">
                                <option value="warm_family">溫馨家庭</option>
                                <option value="premium">質感</option>
                                <option value="energetic">活力</option>
                                <option value="luxury">豪宅</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- 角色 DNA -->
                <section class="bg-white rounded-lg border p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">角色設定</h3>
                    <div>
                        <InputLabel value="角色暱稱" />
                        <TextInput v-model="form.character_nickname" class="mt-1 block w-full" placeholder="例：豆豆" />
                    </div>
                    <div>
                        <InputLabel value="角色 DNA（英文 prompt）" />
                        <textarea v-model="form.character_dna" rows="4" :class="['mt-1 font-mono', inputClass]" placeholder="a chubby orange tabby cat with cute round face, wearing a soft blue knitted turtleneck sweater..." />
                    </div>
                </section>

                <!-- 故事大綱 -->
                <section class="bg-white rounded-lg border p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">故事設定</h3>
                    <div>
                        <InputLabel value="故事大綱" />
                        <textarea v-model="form.story_outline" rows="3" :class="['mt-1', inputClass]" placeholder="從 Cowork 複製故事大綱..." />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="必要元素" />
                            <textarea v-model="form.must_have" rows="2" :class="['mt-1', inputClass]" placeholder="一定要包含的內容..." />
                        </div>
                        <div>
                            <InputLabel value="禁忌" />
                            <textarea v-model="form.taboos" rows="2" :class="['mt-1', inputClass]" placeholder="不能出現的內容..." />
                        </div>
                    </div>
                </section>

                <!-- Shots -->
                <section class="bg-white rounded-lg border p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">分鏡腳本（從 Cowork 貼入）</h3>
                        <button type="button" @click="addShot" class="text-sm text-indigo-600 hover:underline">+ 新增鏡頭</button>
                    </div>

                    <!-- Shot 標籤列 -->
                    <div class="flex gap-1 flex-wrap">
                        <button
                            v-for="(s, i) in shots"
                            :key="s.shot_id"
                            type="button"
                            :class="[
                                'px-3 py-1 rounded-md text-xs font-medium transition',
                                activeShot === i ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                            @click="activeShot = i"
                        >
                            {{ s.shot_id }} ({{ s.duration_seconds }}s)
                        </button>
                    </div>

                    <!-- 當前 Shot 編輯 -->
                    <div v-if="shots[activeShot]" class="space-y-3 border-t pt-4">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-700">{{ shots[activeShot].shot_id }}</span>
                            <div class="flex items-center gap-3">
                                <InputLabel value="秒數" />
                                <input v-model.number="shots[activeShot].duration_seconds" type="number" min="1" max="30" class="w-16 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                                <button type="button" @click="removeShot(activeShot)" class="text-red-500 text-sm hover:underline">刪除</button>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="場景描述" />
                            <textarea v-model="shots[activeShot].scene_description" rows="2" :class="['mt-1', inputClass]" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="旁白" />
                                <textarea v-model="shots[activeShot].voiceover_text" rows="2" :class="['mt-1', inputClass]" />
                            </div>
                            <div>
                                <InputLabel value="字幕" />
                                <textarea v-model="shots[activeShot].subtitle" rows="2" :class="['mt-1', inputClass]" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Flux Prompt（圖片生成用）" />
                            <textarea v-model="shots[activeShot].flux_prompt" rows="3" :class="['mt-1 font-mono', inputClass]" />
                        </div>
                        <div>
                            <InputLabel value="Kling Prompt（動畫生成用）" />
                            <textarea v-model="shots[activeShot].kling_prompt" rows="2" :class="['mt-1 font-mono', inputClass]" />
                        </div>
                        <div>
                            <InputLabel value="情緒" />
                            <TextInput v-model="shots[activeShot].emotion" class="mt-1 block w-full" placeholder="例：好奇、溫暖、滿足" />
                        </div>
                    </div>
                </section>

                <!-- 送出 -->
                <div class="flex justify-end gap-3">
                    <ActionButton variant="secondary" @click="router.push('/')">取消</ActionButton>
                    <ActionButton :loading="submitting" @click="submit">建立建案並開始</ActionButton>
                </div>

                <p v-if="store.error" class="text-red-600 text-sm">{{ store.error }}</p>
            </form>
        </div>
    </div>
</template>
