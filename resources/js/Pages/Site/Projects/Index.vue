<script setup>
import { ref, computed } from 'vue';
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
    projects: Array,
    activeStatus: Number,
    statusList: Array,
});

const { seo } = useSeo({
    title: props.page?.seo_title || props.page?.title || '建築業績',
    description: props.metaDescription || props.page?.seo_description || props.page?.summary || '',
    image: props.page?.cover_image_path,
    breadcrumbs: [
        { name: '首頁', url: '/' },
        { name: '建築業績', url: '/projects' },
    ],
    faqItems: props.page?.faq_items,
});

const currentStatus = ref(null);

const filteredProjects = computed(() => {
    if (!currentStatus.value) return props.projects;
    return props.projects.filter(p => p.project_status_id === currentStatus.value);
});
</script>

<template>
    <SiteLayout :title="page?.title || '建築業績'" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview" :seo="seo">
        <section class="mx-auto max-w-7xl px-6 py-20">
            <div>
                <h1 class="text-5xl font-semibold">{{ page?.title || '建築業績' }}</h1>
                <div v-if="page?.content" class="mt-4 max-w-none prose prose-stone prose-p:my-0 prose-p:leading-8" v-html="page.content" />
                <div class="mt-8 flex flex-wrap gap-3">
                    <button
                        @click="currentStatus = null"
                        class="rounded-full border px-4 py-2 text-sm transition"
                        :class="!currentStatus
                            ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                            : 'border-stone-300 text-stone-600'"
                    >
                        所有作品
                    </button>
                    <button
                        v-for="status in statusList"
                        :key="status.id"
                        @click="currentStatus = status.id"
                        class="rounded-full border px-4 py-2 text-sm transition"
                        :class="currentStatus === status.id
                            ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                            : 'border-stone-300 text-stone-600'"
                    >
                        {{ status.name }}
                    </button>
                </div>
            </div>

            <div class="mt-16 space-y-8">
                <article
                    v-for="(project, index) in filteredProjects"
                    :key="project.id"
                    class="overflow-hidden rounded-[2rem] border"
                    :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-white' : 'border-stone-200 bg-white'"
                >
                    <div class="grid h-full lg:grid-cols-2">
                        <div
                            class="min-h-[320px] bg-cover bg-center"
                            :class="index % 2 === 1 ? 'lg:order-2' : ''"
                            :style="{ backgroundImage: project.featured_image_path ? `url('${mediaUrl(project.featured_image_path)}')` : 'linear-gradient(135deg, rgba(24,76,97,0.18), rgba(181,154,106,0.3))' }"
                        />
                        <div class="flex flex-col justify-center p-8 lg:p-10">
                            <p class="text-sm text-[var(--site-primary)]">
                                {{ project.launch_year }} — {{ project.project_status?.name }}
                            </p>
                            <h2 class="mt-3 text-3xl font-semibold">{{ project.name }}</h2>
                            <div class="mt-4 w-10 border-t border-stone-300"></div>
                            <p class="mt-4 text-sm leading-7 text-stone-600">{{ project.summary }}</p>
                            <div class="mt-8">
                                <a :href="`${routeMap.projects}/${project.slug}`" class="inline-flex rounded-full border border-stone-400 px-6 py-2.5 text-sm text-stone-700 transition hover:border-[var(--site-primary)] hover:text-[var(--site-primary)]">
                                    了解更多
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <FaqSection v-if="page?.faq_items?.length" :items="page.faq_items" />
    </SiteLayout>
</template>
