<script setup>
import { computed } from 'vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { useMedia } from '@/composables/useMedia';

const { mediaUrl } = useMedia();

const props = defineProps({
    site: Object,
    navigation: Object,
    routeMap: Object,
    isPreview: Boolean,
    page: Object,
    articles: Array,
    categories: { type: Array, default: () => [] },
    activeCategory: { type: String, default: '' },
});

const isClassic = computed(() => props.site.theme_key !== 'builder-editorial');
</script>

<template>
    <SiteLayout :title="page?.title || '最新消息'" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview">
        <section class="mx-auto max-w-7xl px-6 py-20">
            <h1 class="text-5xl font-semibold">{{ page?.title || '最新消息' }}</h1>
            <p class="mt-8 max-w-3xl text-lg leading-8 text-stone-600">
                {{ page?.summary || '建案、活動與工程推進的內容統一由編輯器維護。' }}
            </p>

            <!-- 分類 tab（classic theme） -->
            <div v-if="isClassic && categories.length" class="mt-8 flex flex-wrap gap-3">
                <a
                    :href="routeMap.news"
                    class="rounded-full border px-4 py-2 text-sm transition"
                    :class="!activeCategory
                        ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                        : 'border-stone-300 text-stone-600'"
                >
                    全部
                </a>
                <a
                    v-for="cat in categories"
                    :key="cat"
                    :href="`${routeMap.news}?category=${encodeURIComponent(cat)}`"
                    class="rounded-full border px-4 py-2 text-sm transition"
                    :class="activeCategory === cat
                        ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                        : 'border-stone-300 text-stone-600'"
                >
                    {{ cat }}
                </a>
            </div>

            <!-- Classic theme — 日期突出卡片 -->
            <div v-if="isClassic" class="mt-12 grid gap-6 lg:grid-cols-3">
                <a v-for="article in articles" :key="article.id" :href="`${routeMap.news}/${article.slug}`" class="group overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white no-underline">
                    <div class="relative h-60 bg-cover bg-center" :style="{ backgroundImage: article.featured_image_path ? `url('${mediaUrl(article.featured_image_path)}')` : 'linear-gradient(135deg,#e0e0e0,#f0f0f0)' }">
                        <div v-if="article.published_at" class="absolute bottom-4 left-4 rounded-lg bg-white px-3 py-2 text-center shadow">
                            <span class="block text-2xl font-bold leading-none text-[#333]">{{ new Date(article.published_at).getDate().toString().padStart(2, '0') }}</span>
                            <span class="block mt-0.5 text-xs uppercase text-stone-500">{{ new Date(article.published_at).toLocaleString('en', { month: 'short' }) }}</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-xs uppercase tracking-[0.3em] text-[var(--site-primary)]">{{ article.category || '最新消息' }}</p>
                        <p class="mt-3 text-lg font-semibold text-[#333] group-hover:text-[var(--site-primary)] transition">{{ article.title }}</p>
                        <p class="mt-3 text-sm leading-7 text-stone-600">{{ article.summary }}</p>
                    </div>
                </a>
            </div>

            <!-- Editorial theme — 原版卡片 -->
            <div v-else class="mt-12 grid gap-6 lg:grid-cols-3">
                <article v-for="article in articles" :key="article.id" class="overflow-hidden rounded-[1.75rem] border border-stone-300 bg-white">
                    <div v-if="article.featured_image_path" class="h-60 bg-cover bg-center" :style="{ backgroundImage: `url('${mediaUrl(article.featured_image_path)}')` }" />
                    <div class="p-6">
                        <p class="text-xs uppercase tracking-[0.3em] text-[var(--site-primary)]">{{ article.category || '最新消息' }}</p>
                        <h2 class="mt-4 text-2xl font-semibold">{{ article.title }}</h2>
                        <p class="mt-4 text-sm leading-7 text-stone-600">{{ article.summary }}</p>
                        <a :href="`${routeMap.news}/${article.slug}`" class="mt-6 inline-flex text-sm transition hover:text-[var(--site-primary)]">閱讀更多</a>
                    </div>
                </article>
            </div>
        </section>
    </SiteLayout>
</template>
