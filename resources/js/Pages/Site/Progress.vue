<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { useMedia } from '@/composables/useMedia';

const { mediaUrl } = useMedia();

const props = defineProps({
    site: Object,
    navigation: Object,
    routeMap: Object,
    isPreview: Boolean,
    page: Object,
    projects: Array,
    isAuthenticated: Boolean,
    selectedProject: Object,
    updates: Array,
    albums: Array,
});

/* 閘門表單 */
const form = useForm({
    project_id: '',
    password: '',
    captcha: '',
    remember: false,
});

const captchaKey = ref(Date.now());
function refreshCaptcha() { captchaKey.value = Date.now(); }

function submit() {
    form.post(props.routeMap.progressAuth, {
        preserveScroll: true,
        onError: () => refreshCaptcha(),
    });
}

function formatMonth(dateStr) {
    if (!dateStr) return '';
    return String(new Date(dateStr).getMonth() + 1).padStart(2, '0');
}

function formatYear(dateStr) {
    if (!dateStr) return '';
    return String(new Date(dateStr).getFullYear());
}
</script>

<template>
    <SiteLayout :title="page?.title || '工程進度'" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview">

        <!-- ===== 閘門：未驗證時顯示 ===== -->
        <template v-if="!isAuthenticated">
            <section class="mx-auto max-w-7xl px-6 py-20">
                <h1 class="text-5xl font-semibold">{{ page?.title || '工程進度' }}</h1>
                <div class="mt-12 grid min-h-[calc(100vh-320px)] items-center gap-12 lg:grid-cols-2">
                <!-- 左：說明文字 -->
                <div class="flex flex-col justify-center">
                    <div class="prose prose-stone max-w-none text-[15px] leading-8 text-stone-600" v-if="page?.content" v-html="page.content"></div>
                    <div class="mt-8 space-y-4 text-[15px] leading-8 text-stone-600" v-else>
                        <p>建築的價值，藏在每一道看不見的工序之中</p>
                        <p>在這裡，我們完整揭露建築的成形過程——<br>從地基開挖、結構施作、水電配置到每一處細節修整，<br>每一道工序皆如實紀錄，透明呈現，<br>這不僅是工程進度的更新，更是對「品質至上、責任承諾」的具體實踐。</p>
                    </div>
                </div>

                <!-- 右：登入表單 -->
                <div class="flex items-start justify-center border-l border-stone-200 pl-10">
                    <form class="w-full space-y-8" @submit.prevent="submit">
                        <!-- 建案名稱 -->
                        <div class="flex items-center gap-4">
                            <label class="flex shrink-0 items-center gap-1 text-sm">
                                <span class="text-red-600 font-bold">*</span>
                                <span class="tracking-[0.3em]">建案名稱</span>
                            </label>
                            <div class="flex-1">
                                <select v-model="form.project_id" class="w-full border-b border-stone-300 bg-transparent pb-2 text-sm outline-none focus:border-[var(--site-primary)]">
                                    <option value="" disabled>請選擇</option>
                                    <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                                </select>
                                <p v-if="form.errors.project_id" class="mt-1 text-xs text-red-500">{{ form.errors.project_id }}</p>
                            </div>
                        </div>

                        <!-- 密碼 -->
                        <div class="flex items-center gap-4">
                            <label class="flex shrink-0 items-center gap-1 text-sm">
                                <span class="text-red-600 font-bold">*</span>
                                <span class="tracking-[0.3em]">密　　碼</span>
                            </label>
                            <div class="flex-1">
                                <input v-model="form.password" type="password" class="w-full border-b border-stone-300 bg-transparent pb-2 text-sm outline-none focus:border-[var(--site-primary)]" />
                                <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                            </div>
                        </div>

                        <!-- 驗證碼 -->
                        <div>
                            <div class="flex items-center gap-4">
                                <label class="flex shrink-0 items-center gap-1 text-sm">
                                    <span class="text-red-600 font-bold">*</span>
                                    <span class="tracking-[0.3em]">驗 證 碼</span>
                                </label>
                                <div class="flex flex-1 items-center gap-3">
                                    <input v-model="form.captcha" type="text" class="w-28 border-b border-stone-300 bg-transparent pb-2 text-sm outline-none focus:border-[var(--site-primary)]" />
                                    <img :src="`/captcha?t=${captchaKey}`" alt="驗證碼" class="h-10 cursor-pointer" @click="refreshCaptcha" title="點擊更換驗證碼" />
                                </div>
                            </div>
                            <p v-if="form.errors.captcha" class="mt-1 text-xs text-red-500">{{ form.errors.captcha }}</p>
                        </div>

                        <!-- 保持登入狀態 -->
                        <div class="flex items-center gap-2">
                            <input id="keep-login" v-model="form.remember" type="checkbox" class="h-4 w-4 border-stone-300" />
                            <label for="keep-login" class="text-sm text-stone-600">保持登入狀態</label>
                        </div>

                        <!-- LOGIN 按鈕 -->
                        <div class="flex justify-end pt-2">
                            <button
                                type="submit"
                                class="flex h-20 w-24 flex-col items-center justify-center bg-[#222] text-white transition hover:bg-[#333]"
                                :disabled="form.processing"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                                <span class="mt-1.5 text-xs font-medium tracking-widest">LOGIN</span>
                            </button>
                        </div>
                    </form>
                </div>
                </div>
            </section>
        </template>

        <!-- ===== 已驗證：工程進度內容 ===== -->
        <template v-else>
            <!-- 上半部：深色背景 + 進度條 -->
            <section class="bg-[#3a3232] px-6 py-20 text-center">
                <div class="mx-auto max-w-4xl">
                    <h1 class="font-serif text-4xl tracking-[0.15em] text-[#c0965c] md:text-5xl">PROGRESS</h1>
                    <p class="mt-3 text-sm tracking-[0.2em] text-[#c0965c]">{{ selectedProject?.name }} — 工程進度</p>
                    <div class="mx-auto mt-6 h-10 w-px bg-[#c0965c]/60"></div>
                    <p class="mx-auto mt-6 max-w-2xl text-sm leading-7 text-stone-400">
                        {{ page?.summary || '透明呈現每一道施工節點與進度百分比。' }}
                    </p>

                    <div class="mt-14 space-y-8 text-left">
                        <div v-for="item in updates" :key="item.id">
                            <p class="text-base font-medium text-white">{{ item.title }}</p>
                            <div class="mt-3 flex items-center gap-4">
                                <div class="relative h-3 flex-1 overflow-hidden rounded-sm bg-[#2a2424]">
                                    <div class="absolute inset-y-0 left-0 rounded-sm" :style="{ width: `${item.progress_percent}%`, background: 'linear-gradient(to right, #c0965c, #d4a76a)' }" />
                                </div>
                                <span class="w-14 text-right font-serif text-sm tracking-wider text-[#c0965c]">{{ item.progress_percent }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 下半部：工程相簿照片 -->
            <section v-if="albums?.length" class="bg-[#3a3232] px-6 pb-20">
                <div class="mx-auto max-w-6xl">
                    <h2 class="text-center font-serif text-3xl tracking-[0.15em] text-[#c0965c] md:text-4xl">PROGRESS VIEW</h2>
                    <p class="mt-3 text-center text-sm tracking-[0.15em] text-stone-400">工程進度照片</p>

                    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <a v-for="album in albums" :key="album.id" :href="`${routeMap.progress}/album/${album.id}`" class="block border border-stone-600 transition-colors hover:border-[#c0965c]">
                            <div class="aspect-[4/3] bg-cover bg-center" :style="{ backgroundImage: `url('${mediaUrl(album.gallery[0])}')` }"></div>
                            <div class="flex items-center justify-between px-5 py-4">
                                <span class="text-sm font-bold text-stone-400">工程進度：{{ album.progress_percent }}%</span>
                                <div class="text-right">
                                    <span class="block font-serif text-4xl leading-none text-stone-300">{{ formatMonth(album.reported_at) }}</span>
                                    <span class="block mt-1 text-xs text-stone-500">{{ formatYear(album.reported_at) }}</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </section>
        </template>

    </SiteLayout>
</template>
