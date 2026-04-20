<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import FaqSection from '@/Components/FaqSection.vue';
import { useMedia } from '@/composables/useMedia';
import { useSeo } from '@/composables/useSeo';

const { mediaUrl } = useMedia();

const props = defineProps({
    site: Object,
    navigation: Object,
    routeMap: Object,
    isPreview: Boolean,
    metaDescription: String,
    page: Object,
    articles: Object,
    categories: { type: Array, default: () => [] },
    activeCategory: { type: Number, default: null },
});

const { seo } = useSeo({
    title: props.page?.seo_title || props.page?.title || '最新消息',
    description: props.metaDescription || props.page?.seo_description || props.page?.summary || '',
    image: props.page?.cover_image_path,
    breadcrumbs: [
        { name: '首頁', url: '/' },
        { name: '最新消息', url: '/news' },
    ],
    faqItems: props.page?.faq_items,
});

const isClassic = computed(() => props.site.theme_key !== 'builder-editorial');
const currentCategory = ref(props.activeCategory);

function filterByCategory(categoryId) {
    currentCategory.value = categoryId;
    const url = categoryId ? `${props.routeMap.news}?category=${categoryId}` : props.routeMap.news;
    router.visit(url, { only: ['articles', 'activeCategory'], preserveState: true, preserveScroll: true });
}
</script>

<template>
    <SiteLayout :title="page?.title || '最新消息'" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview" :seo="seo">
        <section class="mx-auto max-w-7xl px-6 py-20">
            <h1 class="text-5xl font-semibold">{{ page?.title || '最新消息' }}</h1>
            <p class="mt-8 max-w-3xl text-lg leading-8 text-stone-600">
                {{ page?.summary }}
            </p>

            <!-- 分類 tab -->
            <div v-if="categories.length" class="mt-8 flex flex-wrap gap-3">
                <button
                    @click="filterByCategory(null)"
                    class="rounded-full border px-4 py-2 text-sm transition"
                    :class="!currentCategory
                        ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                        : 'border-stone-300 text-stone-600'"
                >
                    全部
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="filterByCategory(cat.id)"
                    class="rounded-full border px-4 py-2 text-sm transition"
                    :class="currentCategory === cat.id
                        ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                        : 'border-stone-300 text-stone-600'"
                >
                    {{ cat.name }}
                </button>
            </div>

            <!-- Classic theme — 日期突出卡片 -->
            <div v-if="isClassic" class="mt-12 grid gap-6 lg:grid-cols-3">
                <a v-for="article in articles.data" :key="article.id" :href="`${routeMap.news}/${article.slug}`" class="group overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white no-underline">
                    <div class="relative h-60 bg-cover bg-center" :style="{ backgroundImage: article.featured_image_path ? `url('${mediaUrl(article.featured_image_path)}')` : 'linear-gradient(135deg,#e0e0e0,#f0f0f0)' }">
                        <div v-if="article.published_at" class="absolute bottom-4 left-4 rounded-lg bg-white px-3 py-2 text-center shadow">
                            <span class="block text-2xl font-bold leading-none text-[#333]">{{ new Date(article.published_at).getDate().toString().padStart(2, '0') }}</span>
                            <span class="block mt-0.5 text-xs uppercase text-stone-500">{{ new Date(article.published_at).toLocaleString('en', { month: 'short' }) }}</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-xs uppercase tracking-[0.3em] text-[var(--site-primary)]">{{ article.news_category?.name || '最新消息' }}</p>
                        <p class="mt-3 text-lg font-semibold text-[#333] group-hover:text-[var(--site-primary)] transition">{{ article.title }}</p>
                        <p class="mt-3 text-sm leading-7 text-stone-600">{{ article.summary }}</p>
                    </div>
                </a>
            </div>

            <!-- Editorial theme — 原版卡片 -->
            <div v-else class="mt-12 grid gap-6 lg:grid-cols-3">
                <article v-for="article in articles.data" :key="article.id" class="overflow-hidden rounded-[1.75rem] border border-stone-300 bg-white">
                    <div v-if="article.featured_image_path" class="h-60 bg-cover bg-center" :style="{ backgroundImage: `url('${mediaUrl(article.featured_image_path)}')` }" />
                    <div class="p-6">
                        <p class="text-xs uppercase tracking-[0.3em] text-[var(--site-primary)]">{{ article.news_category?.name || '最新消息' }}</p>
                        <h2 class="mt-4 text-2xl font-semibold">{{ article.title }}</h2>
                        <p class="mt-4 text-sm leading-7 text-stone-600">{{ article.summary }}</p>
                        <a :href="`${routeMap.news}/${article.slug}`" class="mt-6 inline-flex text-sm transition hover:text-[var(--site-primary)]">閱讀更多</a>
                    </div>
                </article>
            </div>

            <!-- 分頁 -->
            <nav v-if="articles.last_page > 1" class="mt-12 flex items-center justify-center gap-2">
                <template v-for="link in articles.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-full border px-4 py-2 text-sm transition"
                        :class="link.active
                            ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                            : 'border-stone-300 text-stone-600 hover:border-stone-400'"
                        v-html="link.label"
                        preserve-scroll
                    />
                    <span
                        v-else
                        class="rounded-full border border-stone-200 px-4 py-2 text-sm text-stone-300"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </section>

        <FaqSection v-if="page?.faq_items?.length" :items="page.faq_items" />
    </SiteLayout>
</template>
