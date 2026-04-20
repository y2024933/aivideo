<script setup>
import SiteLayout from '@/Layouts/SiteLayout.vue';

defineProps({
    site: Object,
    navigation: Object,
    routeMap: Object,
    isPreview: Boolean,
    page: Object,
    serviceHighlights: Array,
    latestNews: Array,
});
</script>

<template>
    <SiteLayout :title="page?.title || '多元服務'" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview">
        <section class="mx-auto grid max-w-7xl gap-12 px-6 py-20 lg:grid-cols-[0.9fr_1.1fr]">
            <div>
                <h1 class="text-5xl font-semibold">{{ page?.title || '多元服務' }}</h1>
                <p class="mt-8 text-lg leading-8 text-stone-600">
                    {{ page?.summary }}
                </p>
                <div class="prose mt-8 max-w-none prose-stone" v-html="page?.content" />
            </div>

            <div class="grid gap-6">
                <article v-for="item in serviceHighlights" :key="item.title" class="rounded-[1.75rem] border p-8" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-white' : 'border-stone-200 bg-white'">
                    <h2 class="text-2xl font-semibold">{{ item.title }}</h2>
                    <p class="mt-4 text-sm leading-7 text-stone-600">{{ item.description }}</p>
                </article>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 pb-16">
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-[1.5rem] border p-6" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-[#f7f0e8]' : 'border-stone-200 bg-[#f8f8f8]'">
                    <h3 class="text-xl font-semibold">協助收租</h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">協助每月租賃管理、收款與催繳租金及費用。</p>
                </article>
                <article class="rounded-[1.5rem] border p-6" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-[#f7f0e8]' : 'border-stone-200 bg-[#f8f8f8]'">
                    <h3 class="text-xl font-semibold">房客服務</h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">日常問題回覆、LINE 客服與入住維護協調。</p>
                </article>
                <article class="rounded-[1.5rem] border p-6" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-[#f7f0e8]' : 'border-stone-200 bg-[#f8f8f8]'">
                    <h3 class="text-xl font-semibold">設備維護</h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">設備維修、家電檢修、清潔與公共空間維護安排。</p>
                </article>
                <article class="rounded-[1.5rem] border p-6" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-[#f7f0e8]' : 'border-stone-200 bg-[#f8f8f8]'">
                    <h3 class="text-xl font-semibold">資產延續</h3>
                    <p class="mt-4 text-sm leading-7 text-stone-600">讓資產管理不只是管理，而是建商品質與品牌承諾的延續。</p>
                </article>
            </div>
        </section>

        <section class="border-t" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-white/70' : 'border-stone-200 bg-[#f8f8f8]'">
            <div class="mx-auto max-w-7xl px-6 py-16">
                <div class="flex items-end justify-between gap-6">
                    <div>
                        <p class="text-sm uppercase tracking-[0.35em] text-stone-500">相關消息</p>
                        <h2 class="mt-3 text-3xl font-semibold">相關消息</h2>
                    </div>
                    <a :href="routeMap.news" class="text-sm transition hover:text-[var(--site-primary)]">查看全部</a>
                </div>
                <div class="mt-8 grid gap-6 lg:grid-cols-3">
                    <article v-for="article in latestNews" :key="article.id" class="rounded-[1.5rem] border p-6" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-white' : 'border-stone-200 bg-white'">
                        <h3 class="text-2xl font-semibold">{{ article.title }}</h3>
                        <p class="mt-3 text-sm leading-7" :class="site.theme_key === 'builder-editorial' ? 'text-stone-600' : 'text-stone-500'">{{ article.summary }}</p>
                    </article>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
