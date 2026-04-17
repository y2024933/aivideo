<script setup>
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { useMedia } from '@/composables/useMedia';

const { mediaUrl } = useMedia();

defineProps({
    site: Object,
    navigation: Object,
    routeMap: Object,
    isPreview: Boolean,
    page: Object,
    projects: Array,
    activeStatus: String,
});

function projectMeta(project) {
    const statuses = {
        selling: '熱銷新案',
        completed: '歷史建案',
    };
    return statuses[project.status] || project.status;
}
</script>

<template>
    <SiteLayout :title="page?.title || '建築業績'" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview">
        <section class="mx-auto max-w-7xl px-6 py-20">
            <div class="grid gap-10 lg:grid-cols-[0.42fr_0.58fr]">
                <div>
                    <h1 class="text-5xl font-semibold">{{ page?.title || '建築業績' }}</h1>
                    <p class="mt-8 text-lg leading-8 text-stone-600">
                        {{ page?.summary || '熱銷新案與歷史建案統一由後台維護，站 A 先用一套共用格式呈現。' }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a
                            :href="routeMap.projects"
                            class="rounded-full border px-4 py-2 text-sm transition"
                            :class="!activeStatus
                                ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                                : 'border-stone-300 text-stone-600'"
                        >
                            所有作品
                        </a>
                        <a
                            :href="`${routeMap.projects}?status=selling`"
                            class="rounded-full border px-4 py-2 text-sm transition"
                            :class="activeStatus === 'selling'
                                ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                                : 'border-stone-300 text-stone-600'"
                        >
                            熱銷新案
                        </a>
                        <a
                            :href="`${routeMap.projects}?status=completed`"
                            class="rounded-full border px-4 py-2 text-sm transition"
                            :class="activeStatus === 'completed'
                                ? 'border-[var(--site-primary)] bg-[var(--site-primary)] text-white'
                                : 'border-stone-300 text-stone-600'"
                        >
                            歷史建案
                        </a>
                    </div>
                </div>
                <div class="rounded-[2rem] border p-8" :class="site.theme_key === 'builder-editorial' ? 'border-stone-300 bg-white' : 'border-stone-200 bg-white'">
                    <div class="prose prose-stone max-w-none" v-html="page?.content" />
                </div>
            </div>

            <div class="mt-16 space-y-8">
                <article
                    v-for="(project, index) in projects"
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
                                {{ project.launch_year }} — {{ projectMeta(project) }}
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
    </SiteLayout>
</template>
