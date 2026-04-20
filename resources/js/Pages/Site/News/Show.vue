<script setup>
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { useMedia } from '@/composables/useMedia';
import { useSeo } from '@/composables/useSeo';

const { mediaUrl } = useMedia();

const props = defineProps({
    site: Object,
    navigation: Object,
    routeMap: Object,
    isPreview: Boolean,
    metaDescription: String,
    article: Object,
    relatedArticles: Array,
});

const { seo } = useSeo({
    title: props.article.title,
    description: props.metaDescription || props.article.summary || '',
    image: props.article.featured_image_path,
    type: 'article',
    breadcrumbs: [
        { name: '首頁', url: '/' },
        { name: '最新消息', url: '/news' },
        { name: props.article.title },
    ],
    jsonLd: {
        '@type': 'Article',
        headline: props.article.title,
        description: props.article.summary,
        datePublished: props.article.published_at,
        publisher: { '@type': 'Organization', name: props.site.name },
    },
});
</script>

<template>
    <SiteLayout :title="article.title" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview" :seo="seo">
        <section class="mx-auto max-w-5xl px-6 py-20">
            <a :href="routeMap.news" class="text-sm transition hover:text-[var(--site-primary)]">返回最新消息</a>
            <p class="mt-8 text-xs uppercase tracking-[0.3em] text-[var(--site-primary)]">{{ article.news_category?.name || '最新消息' }}</p>
            <h1 class="mt-4 text-5xl font-semibold">{{ article.title }}</h1>
            <p class="mt-6 text-lg leading-8 text-stone-600">{{ article.summary }}</p>
            <img v-if="article.featured_image_path" :src="mediaUrl(article.featured_image_path)" :alt="article.featured_image_alt || article.title" class="mt-10 mb-8 w-full rounded-lg object-cover max-h-[400px]" />
            <div class="prose mt-10 max-w-none prose-stone" v-html="article.content" />
        </section>

        <section v-if="relatedArticles.length" class="border-t" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-white/70' : 'border-stone-200 bg-[#f8f8f8]'">
            <div class="mx-auto max-w-7xl px-6 py-16">
                <h2 class="text-3xl font-semibold">更多消息</h2>
                <div class="mt-8 grid gap-6 lg:grid-cols-3">
                    <article v-for="item in relatedArticles" :key="item.id" class="rounded-[1.5rem] border p-6" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-white' : 'border-stone-200 bg-white'">
                        <h3 class="text-2xl font-semibold">{{ item.title }}</h3>
                        <p class="mt-3 text-sm leading-7 text-stone-600">{{ item.summary }}</p>
                        <a :href="`${routeMap.news}/${item.slug}`" class="mt-6 inline-flex text-sm transition hover:text-[var(--site-primary)]">閱讀內容</a>
                    </article>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
