<script setup>
import { computed } from 'vue';
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
    project: Object,
    progressUpdates: Array,
    relatedProjects: Array,
});

const { seo } = useSeo({
    title: props.project.name,
    description: props.metaDescription || props.project.summary || '',
    image: props.project.featured_image_path || props.project.cover_image_path,
    breadcrumbs: [
        { name: '首頁', url: '/' },
        { name: '建築業績', url: '/projects' },
        { name: props.project.name },
    ],
});

const isClassic = computed(() => props.site.theme_key !== 'builder-editorial');
</script>

<template>
    <SiteLayout :title="project.name" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview" :seo="seo">
        <!-- 主要資訊區：左大圖 + 右側資訊 -->
        <section class="mx-auto max-w-7xl px-6 pt-20 pb-10">
            <a :href="routeMap.projects" class="text-sm text-stone-500 transition hover:text-[var(--site-primary)]">← 返回建築業績</a>
            <div class="mt-6 grid gap-0 lg:grid-cols-2">
                <!-- 左：大圖 -->
                <div class="min-h-[250px] md:min-h-[400px] lg:min-h-[520px] bg-cover bg-center" :style="{ backgroundImage: project.featured_image_path ? `url('${mediaUrl(project.featured_image_path)}')` : 'linear-gradient(135deg, rgba(44,62,80,0.3), rgba(52,73,94,0.5))' }"></div>

                <!-- 右：資訊 -->
                <div class="flex flex-col justify-between bg-white p-8 lg:p-10">
                    <div>
                        <div class="flex items-baseline gap-3">
                            <h1 class="text-2xl font-semibold text-[#333]">{{ project.name }}</h1>
                            <span class="text-base text-[#aaa]">{{ project.launch_year }}</span>
                        </div>
                        <p class="mt-3 text-sm leading-7 text-[#777]">{{ project.summary }}</p>

                        <!-- 規格表 -->
                        <div class="mt-8 pt-6" style="border-top:5px solid transparent; border-image:linear-gradient(to left, #3a6a7a, #a8d8ea) 1">
                            <dl class="space-y-5">
                                <div v-if="project.address || project.location" class="flex gap-6">
                                    <dt class="w-20 shrink-0 text-sm font-medium text-[#3a6a7a]">基地位置</dt>
                                    <dd class="text-sm text-[#444]">{{ project.address || project.location }}</dd>
                                </div>
                                <div v-if="project.households" class="flex gap-6">
                                    <dt class="w-20 shrink-0 text-sm font-medium text-[#3a6a7a]">規　　劃</dt>
                                    <dd class="text-sm text-[#444]">{{ project.households }}</dd>
                                </div>
                                <div v-if="project.floors" class="flex gap-6">
                                    <dt class="w-20 shrink-0 text-sm font-medium text-[#3a6a7a]">樓　　層</dt>
                                    <dd class="text-sm text-[#444]">{{ project.floors }}</dd>
                                </div>
                                <div v-if="project.area" class="flex gap-6">
                                    <dt class="w-20 shrink-0 text-sm font-medium text-[#3a6a7a]">坪　　數</dt>
                                    <dd class="text-sm text-[#444]">{{ project.area }}</dd>
                                </div>
                                <div v-if="project.layout_plan" class="flex gap-6">
                                    <dt class="w-20 shrink-0 text-sm font-medium text-[#3a6a7a]">格　　局</dt>
                                    <dd class="text-sm text-[#444]">{{ project.layout_plan }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- 底部 icon 按鈕列 -->
                    <div class="mt-8 flex flex-wrap gap-0 border-t border-stone-200 pt-6">
                        <a :href="`tel:${project.sales_info?.sales_phone || site.footer_content?.phone || ''}`" class="group flex flex-1 flex-col items-center gap-2.5 py-3 text-center text-xs text-[#5b9a3c] transition">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-[#5b9a3c] transition group-hover:bg-[#5b9a3c] group-hover:text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" /></svg>
                            </span>
                            聯絡電話
                        </a>
                        <a :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(project.address || project.location || '')}`" target="_blank" rel="noopener noreferrer" class="group flex flex-1 flex-col items-center gap-2.5 border-l border-stone-200 py-3 text-center text-xs text-[#5b9a3c] transition">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-[#5b9a3c] transition group-hover:bg-[#5b9a3c] group-hover:text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1m1.5.5l-1.5-.5M6.75 7.364V3h-3v18m3-13.636l10.5-3.819" /></svg>
                            </span>
                            基地位置
                        </a>
                        <a v-if="project.sales_info?.booking_url" :href="project.sales_info.booking_url" target="_blank" rel="noopener noreferrer" class="group flex flex-1 flex-col items-center gap-2.5 border-l border-stone-200 py-3 text-center text-xs text-[#5b9a3c] transition">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-[#5b9a3c] transition group-hover:bg-[#5b9a3c] group-hover:text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                            </span>
                            建案網站
                        </a>
                        <a v-if="site.social_links?.facebook" :href="site.social_links.facebook" target="_blank" rel="noopener noreferrer" class="group flex flex-1 flex-col items-center gap-2.5 border-l border-stone-200 py-3 text-center text-xs text-[#5b9a3c] transition">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-[#5b9a3c] transition group-hover:bg-[#5b9a3c] group-hover:text-white">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" /></svg>
                            </span>
                            粉絲專頁
                        </a>
                        <a :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(site.footer_content?.address || project.address || '')}`" target="_blank" rel="noopener noreferrer" class="group flex flex-1 flex-col items-center gap-2.5 border-l border-stone-200 py-3 text-center text-xs text-[#5b9a3c] transition">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-[#5b9a3c] transition group-hover:bg-[#5b9a3c] group-hover:text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                            </span>
                            接待中心
                        </a>
                    </div>
                </div>
            </div>
        </section>



    </SiteLayout>
</template>
