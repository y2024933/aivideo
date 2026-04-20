<script setup>
import { computed } from 'vue';
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
    aboutPage: Object,
    projectsPage: Object,
    newsPage: Object,
    servicesPage: Object,
    progressPage: Object,
    contactPage: Object,
    featuredProjects: Array,
    latestNews: Array,
    latestProgress: Array,
    classicProjects: Array,
});

const { seo } = useSeo({
    title: props.site.seo_defaults?.title || props.site.name,
    description: props.metaDescription || props.site.seo_defaults?.description || '',
    image: props.site.logo_path,
    type: 'website',
    breadcrumbs: [{ name: '首頁', url: '/' }],
    jsonLd: {
        '@type': 'LocalBusiness',
        name: props.site.name,
        address: props.site.footer_content?.address,
        telephone: props.site.footer_content?.phone,
        email: props.site.footer_content?.email,
        ...(props.site.footer_content?.opening_hours && {
            openingHours: props.site.footer_content.opening_hours.split(',').map(s => s.trim()),
        }),
        ...(props.site.footer_content?.area_served && {
            areaServed: props.site.footer_content.area_served.split(',').map(s => s.trim()),
        }),
    },
    faqItems: props.site.seo_defaults?.faq_items,
});

const isClassic = computed(() => props.site.theme_key !== 'builder-editorial');
const isEditorial = computed(() => !isClassic.value);
const heroBg = computed(() => mediaUrl(props.site.hero_content?.background_image || props.featuredProjects?.[0]?.featured_image_path));
</script>

<template>
    <SiteLayout :title="site.seo_defaults?.title || site.name" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview" :seo="{ meta, jsonLdScript }">

        <!-- ===================================================================== -->
        <!-- CLASSIC THEME — 5 Section 全屏式首頁                                    -->
        <!-- ===================================================================== -->
        <template v-if="isClassic">

            <!-- Hero Banner -->
            <section class="hero-banner">
                <div class="hero-slide">
                    <div class="hero-bg-img" :style="{ backgroundImage: heroBg ? `url('${heroBg}')` : 'linear-gradient(135deg, #2c3e50, #3d566e)' }"></div>
                    <div class="hero-overlay">
                        <div class="hero-text" data-animate>
                            <p class="hero-eyebrow">{{ site.hero_content?.eyebrow || site.brand_name }}</p>
                            <h1>{{ site.hero_content?.headline || '文化為本・世代傳家' }}</h1>
                            <p class="hero-sub">{{ site.hero_content?.subheadline || '' }}</p>
                        </div>
                    </div>
                </div>
                <!-- 建案縮圖列 -->
                <div v-if="featuredProjects?.length" class="hero-projects">
                    <div class="hero-projects-inner">
                        <a v-for="project in featuredProjects.slice(0, 3)" :key="project.id" :href="`${routeMap.projects}/${project.slug}`" class="hero-project-card">
                            <div class="card-img" :style="{ backgroundImage: project.featured_image_path ? `url('${mediaUrl(project.featured_image_path)}')` : 'linear-gradient(135deg, #34495e, #2c3e50)' }"></div>
                            <div class="card-name">{{ project.name }}</div>
                        </a>
                    </div>
                </div>
            </section>

        </template>

        <!-- ===================================================================== -->
        <!-- EDITORIAL THEME — 保持原版（完全不動）                                   -->
        <!-- ===================================================================== -->
        <template v-else>
            <section class="border-b" :class="'border-stone-300'">
                <div class="mx-auto grid max-w-7xl gap-12 px-6 py-20 lg:grid-cols-[0.9fr_1.1fr]">
                    <div>
                        <p class="text-sm uppercase tracking-[0.35em] text-[var(--site-primary)]">{{ site.hero_content?.eyebrow || site.brand_name }}</p>
                        <h1 class="mt-6 max-w-4xl text-5xl font-semibold leading-tight md:text-6xl">{{ site.hero_content?.headline }}</h1>
                        <p class="mt-8 max-w-2xl text-lg leading-8 text-stone-600">{{ site.hero_content?.subheadline }}</p>
                        <div class="mt-10 flex flex-wrap gap-4">
                            <a :href="site.hero_content?.cta_link || routeMap.projects" class="rounded-full bg-[var(--site-primary)] px-6 py-3 text-sm font-medium text-white transition hover:opacity-90">{{ site.hero_content?.cta_label || '查看建案' }}</a>
                            <a :href="routeMap.contact" class="rounded-full border border-stone-300 px-6 py-3 text-sm text-stone-700 transition hover:border-[var(--site-primary)] hover:text-[var(--site-primary)]">聯絡我們</a>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-[2rem] border border-stone-300 bg-white">
                        <div class="grid h-full min-h-[360px] lg:grid-rows-[1fr_auto]">
                            <div class="min-h-[320px] bg-cover bg-center" :style="{ backgroundImage: `linear-gradient(rgba(15,23,42,0.1), rgba(15,23,42,0.15)), url('${mediaUrl(site.hero_content?.background_image || featuredProjects[0]?.featured_image_path)}')` }" />
                            <div class="grid gap-4 bg-[#f7f0e8] p-6 md:grid-cols-3">
                                <article v-for="project in featuredProjects" :key="project.id" class="rounded-[1.25rem] bg-white p-4">
                                    <p class="text-xs uppercase tracking-[0.3em] text-[var(--site-primary)]">{{ project.launch_year }} / {{ project.project_status?.name }}</p>
                                    <h3 class="mt-3 text-lg font-semibold">{{ project.name }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-stone-600">{{ project.summary || project.location }}</p>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-16">
                <div class="flex items-end justify-between gap-6">
                    <div>
                        <p class="text-sm uppercase tracking-[0.35em] text-stone-500">{{ site.about_content?.eyebrow || '關於我們' }}</p>
                        <h2 class="mt-3 text-3xl font-semibold">{{ aboutPage?.title || site.about_content?.headline || '關於我們' }}</h2>
                    </div>
                    <a :href="routeMap.about" class="text-sm transition hover:text-[var(--site-primary)]">閱讀更多</a>
                </div>
                <div class="mt-8 grid gap-8 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="rounded-[2rem] border border-stone-300 bg-white p-8">
                        <p class="text-lg leading-9 text-stone-700">{{ site.about_content?.summary || aboutPage?.summary || '' }}</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-1">
                        <div v-for="(item, index) in (site.about_content?.highlights || [])" :key="item.title || index" class="rounded-[2rem] p-6" :class="index === 0 ? 'bg-[var(--site-secondary)] text-stone-800' : 'border border-stone-300 bg-white text-stone-800'">
                            <p class="text-sm uppercase tracking-[0.3em]">{{ item.title }}</p>
                            <p class="mt-3 text-lg">{{ item.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-y border-stone-300 bg-white/70">
                <div class="mx-auto max-w-7xl px-6 py-16">
                    <div class="grid gap-8 lg:grid-cols-[0.42fr_0.58fr]">
                        <div>
                            <p class="text-sm uppercase tracking-[0.35em] text-stone-500">建築作品</p>
                            <h2 class="mt-3 text-3xl font-semibold">{{ projectsPage?.title || '建築業績' }}</h2>
                            <p class="mt-5 text-sm leading-8 text-stone-600">{{ projectsPage?.summary || '' }}</p>
                            <a :href="routeMap.projects" class="mt-8 inline-flex rounded-full bg-[var(--site-primary)] px-5 py-3 text-sm text-white transition">全部建案</a>
                        </div>
                        <div class="grid gap-6 md:grid-cols-3">
                            <article v-for="project in featuredProjects" :key="project.id" class="overflow-hidden rounded-[1.75rem] border border-stone-300 bg-white">
                                <div class="h-52 bg-cover bg-center" :style="{ backgroundImage: project.featured_image_path ? `url('${mediaUrl(project.featured_image_path)}')` : 'linear-gradient(135deg, rgba(24,76,97,0.18), rgba(181,154,106,0.3))' }" />
                                <div class="p-6">
                                    <p class="text-xs uppercase tracking-[0.3em] text-[var(--site-primary)]">{{ project.launch_year }} / {{ project.project_status?.name }}</p>
                                    <h3 class="mt-4 text-2xl font-semibold">{{ project.name }}</h3>
                                    <p class="mt-3 text-sm leading-7 text-stone-600">{{ project.summary }}</p>
                                    <a :href="`${routeMap.projects}/${project.slug}`" class="mt-5 inline-flex text-sm transition hover:text-[var(--site-primary)]">了解更多</a>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto grid max-w-7xl gap-10 px-6 py-16 lg:grid-cols-[0.95fr_1.05fr]">
                <div>
                    <p class="text-sm uppercase tracking-[0.35em] text-stone-500">最新消息</p>
                    <h2 class="mt-3 text-3xl font-semibold">{{ newsPage?.title || '最新消息' }}</h2>
                    <p class="mt-5 max-w-xl text-sm leading-8 text-stone-600">{{ newsPage?.summary || '' }}</p>
                    <div class="mt-8 space-y-6">
                        <article v-for="article in latestNews" :key="article.id" class="border-b border-stone-200 pb-6">
                            <p class="text-xs uppercase tracking-[0.3em] text-[var(--site-primary)]">{{ article.news_category?.name || '最新消息' }}</p>
                            <h3 class="mt-3 text-xl font-semibold">{{ article.title }}</h3>
                            <p class="mt-3 text-sm leading-7 text-stone-600">{{ article.summary }}</p>
                            <a :href="`${routeMap.news}/${article.slug}`" class="mt-4 inline-flex text-sm transition hover:text-[var(--site-primary)]">閱讀文章</a>
                        </article>
                    </div>
                </div>
                <div class="rounded-[2rem] border border-stone-300 bg-[#fbf6f0] p-8">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-sm uppercase tracking-[0.35em] text-stone-500">工程進度</p>
                            <h2 class="mt-3 text-3xl font-semibold">{{ progressPage?.title || '工程進度' }}</h2>
                        </div>
                        <a :href="routeMap.progress" class="text-sm transition hover:text-[var(--site-primary)]">查看全部</a>
                    </div>
                    <p class="mt-5 text-sm leading-8 text-stone-600">{{ progressPage?.summary || '' }}</p>
                    <div class="mt-8 space-y-5">
                        <article v-for="item in latestProgress" :key="item.id">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ item.title }}</h3>
                                    <p class="mt-2 text-sm text-stone-600">{{ item.summary }}</p>
                                </div>
                                <strong class="text-2xl text-[var(--site-primary)]">{{ item.progress_percent }}%</strong>
                            </div>
                            <div class="mt-4 h-2 rounded-full bg-stone-200">
                                <div class="h-2 rounded-full" :style="{ width: `${item.progress_percent}%`, backgroundColor: 'var(--site-primary)' }" />
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-16">
                <div class="rounded-[2.25rem] border border-stone-300 bg-white p-8 md:p-10">
                    <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
                        <div>
                            <p class="text-sm uppercase tracking-[0.35em] text-stone-500">{{ site.contact_content?.eyebrow || '聯絡我們' }}</p>
                            <h2 class="mt-4 text-3xl font-semibold">{{ contactPage?.title || site.contact_content?.headline || '聯絡我們' }}</h2>
                            <p class="mt-5 text-sm leading-8 text-stone-600">{{ site.contact_content?.summary || contactPage?.summary || '' }}</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div v-for="(item, index) in (site.contact_content?.highlights || [])" :key="item.title || index" class="rounded-[1.5rem] bg-[#f7f0e8] p-5">
                                <p class="text-sm text-stone-500">{{ item.title }}</p>
                                <p class="mt-3 font-semibold">{{ item.description }}</p>
                            </div>
                        </div>
                    </div>
                    <a :href="routeMap.contact" class="mt-8 inline-flex rounded-full bg-[var(--site-primary)] px-6 py-3 text-sm font-medium text-white transition hover:opacity-90">{{ site.contact_content?.cta_label || '前往聯絡表單' }}</a>
                </div>
            </section>
        </template>

        <FaqSection v-if="site.seo_defaults?.faq_items?.length" :items="site.seo_defaults.faq_items" />

    </SiteLayout>
</template>
