<script setup>
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    site: Object,
    navigation: Object,
    routeMap: Object,
    isPreview: Boolean,
    page: Object,
    projects: Array,
    inquiryTypes: Array,
});

const flash = usePage().props.flash;
const form = useForm({
    inquiry_type: props.inquiryTypes[0] ?? '',
    project_id: '',
    name: '',
    phone: '',
    email: '',
    line_id: '',
    contact_time: '',
    message: '',
    captcha: '',
});

const captchaKey = ref(Date.now());
function refreshCaptcha() { captchaKey.value = Date.now(); }

const showSuccess = ref(false);

function submit() {
    form.post(props.routeMap.contactSubmit, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            refreshCaptcha();
            showSuccess.value = true;
            setTimeout(() => { showSuccess.value = false; }, 5000);
        },
    });
}
</script>

<template>
    <SiteLayout :title="page?.title || '聯絡我們'" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview">
        <section class="mx-auto max-w-7xl px-6 py-20">
            <h1 class="text-5xl font-semibold">{{ page?.title || '聯絡我們' }}</h1>

            <div v-if="flash.success || showSuccess" class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ flash.success || '已收到您的訊息，我們會盡快與您聯繫。' }}
            </div>

            <div class="mt-12 grid min-h-[calc(100vh-320px)] items-center gap-12 lg:grid-cols-2">
                <!-- 左：文案 -->
                <div class="flex flex-col justify-center">
                    <div v-if="page?.content" class="prose prose-stone max-w-none text-[15px] leading-8 text-stone-500 [&_p:empty]:min-h-[1em]" v-html="page.content"></div>
                </div>

                <!-- 右：表單 -->
                <div class="flex items-start justify-center border-l border-stone-200 pl-10">
                <div class="w-full">
                <p class="mb-5 text-sm text-stone-500">歡迎留下您的寶貴意見，我們將派專人為您服務。謝謝!! (<span class="text-red-500 text-xs">＊</span> 為必填 )</p>
                <form class="space-y-2" @submit.prevent="submit">
                    <div class="flex items-center gap-3">
                        <label class="w-20 shrink-0 text-sm"><span class="text-red-500 text-xs">＊</span>姓名</label>
                        <input v-model="form.name" type="text" class="flex-1 rounded-xl border border-stone-300 bg-transparent px-3 py-1.5 text-sm" />
                    </div>
                    <p v-if="form.errors.name" class="pl-24 text-xs text-rose-400">{{ form.errors.name }}</p>
                    <div class="flex items-center gap-3">
                        <label class="w-20 shrink-0 text-sm"><span class="text-red-500 text-xs">＊</span>電話</label>
                        <input v-model="form.phone" type="text" class="flex-1 rounded-xl border border-stone-300 bg-transparent px-3 py-1.5 text-sm" />
                    </div>
                    <p v-if="form.errors.phone" class="pl-24 text-xs text-rose-400">{{ form.errors.phone }}</p>
                    <div class="flex items-center gap-3">
                        <label class="w-20 shrink-0 text-sm"><span class="text-xs text-transparent">＊</span>電子郵件</label>
                        <input v-model="form.email" type="email" class="flex-1 rounded-xl border border-stone-300 bg-transparent px-3 py-1.5 text-sm" />
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="w-20 shrink-0 text-sm"><span class="text-red-500 text-xs">＊</span>留言類別</label>
                        <select v-model="form.inquiry_type" class="flex-1 rounded-xl border border-stone-300 bg-transparent px-3 py-1.5 text-sm">
                            <option value="" disabled>請選擇</option>
                            <option v-for="type in inquiryTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="w-20 shrink-0 text-sm"><span class="text-xs text-transparent">＊</span>建案名稱</label>
                        <select v-model="form.project_id" class="flex-1 rounded-xl border border-stone-300 bg-transparent px-3 py-1.5 text-sm">
                            <option value="">請選擇</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm"><span class="text-red-500 text-xs">＊</span>留言內容</label>
                        <textarea v-model="form.message" rows="3" class="w-full rounded-xl border border-stone-300 bg-transparent px-3 py-1.5 text-sm" />
                        <p v-if="form.errors.message" class="mt-1 text-xs text-rose-400">{{ form.errors.message }}</p>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <label class="shrink-0 text-sm"><span class="text-red-500 text-xs">＊</span>驗證碼</label>
                            <input v-model="form.captcha" type="text" placeholder="請輸入驗證碼" class="w-40 rounded-xl border border-stone-300 bg-transparent px-3 py-1.5 text-sm" />
                            <img :src="`/captcha?t=${captchaKey}`" alt="驗證碼" class="h-8 cursor-pointer rounded" @click="refreshCaptcha" title="點擊更換驗證碼" />
                        </div>
                        <button type="submit" class="rounded-full px-6 py-1.5 text-sm font-medium transition bg-[var(--site-primary)] text-white hover:opacity-90" :disabled="form.processing">
                            {{ form.processing ? '送出中...' : '送出表單' }}
                        </button>
                    </div>
                    <p v-if="form.errors.captcha" class="text-xs text-rose-400">{{ form.errors.captcha }}</p>
                </form>
                </div>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
