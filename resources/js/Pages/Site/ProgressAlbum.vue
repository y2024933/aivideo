<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { useMedia } from '@/composables/useMedia';
import { useSeo } from '@/composables/useSeo';

const { mediaUrl } = useMedia();

const props = defineProps({
    site: Object,
    navigation: Object,
    routeMap: Object,
    isPreview: Boolean,
    album: Object,
});

const { seo } = useSeo({
    title: `工程相簿 — ${props.album.project?.name || ''}`,
    description: props.album.description || '',
    breadcrumbs: [
        { name: '首頁', url: '/' },
        { name: '工程進度', url: '/progress' },
        { name: '工程相簿' },
    ],
});

/* Lightbox */
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const touchStartX = ref(0);

function openLightbox(index) {
    lightboxIndex.value = index;
    lightboxOpen.value = true;
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    lightboxOpen.value = false;
    document.body.style.overflow = '';
}
function prev() { if (lightboxIndex.value > 0) lightboxIndex.value--; }
function next() { if (lightboxIndex.value < props.album.gallery.length - 1) lightboxIndex.value++; }

function onKeydown(e) {
    if (!lightboxOpen.value) return;
    if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'Escape') closeLightbox();
}
function onTouchStart(e) { touchStartX.value = e.touches[0].clientX; }
function onTouchEnd(e) {
    const diff = e.changedTouches[0].clientX - touchStartX.value;
    if (Math.abs(diff) > 50) diff > 0 ? prev() : next();
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return `${d.getFullYear()} \u5E74 ${d.getMonth() + 1} \u6708`;
}
</script>

<template>
    <SiteLayout :title="`\u5DE5\u7A0B\u76F8\u7C3F \u2014 ${formatDate(album.reported_at)}`" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview" :seo="seo">

        <section class="bg-[#3a3232] min-h-screen px-6 py-20">
            <div class="mx-auto max-w-6xl">
                <!-- 返回 + 標題 -->
                <a :href="routeMap.progress" class="inline-flex items-center gap-2 text-sm text-stone-400 transition hover:text-[#c0965c]">
                    &larr; 返回工程進度
                </a>

                <div class="mt-8 text-center">
                    <h1 class="font-serif text-3xl tracking-[0.15em] text-[#c0965c] md:text-4xl">PROGRESS VIEW</h1>
                    <p class="mt-3 text-sm text-stone-400">
                        {{ album.project?.name }} &mdash; {{ formatDate(album.reported_at) }}
                    </p>
                    <p class="mt-2 text-lg font-bold text-[#c0965c]">工程進度：{{ album.progress_percent }}%</p>
                </div>

                <!-- 照片 Grid -->
                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(img, i) in album.gallery"
                        :key="i"
                        class="group cursor-pointer overflow-hidden border border-stone-600 transition-colors hover:border-[#c0965c]"
                        @click="openLightbox(i)"
                    >
                        <div
                            class="aspect-[4/3] bg-cover bg-center transition-transform duration-300 group-hover:scale-105"
                            :style="{ backgroundImage: `url('${mediaUrl(img)}')` }"
                        ></div>
                    </div>
                </div>

                <p class="mt-8 text-center text-sm text-stone-500">共 {{ album.gallery?.length || 0 }} 張照片</p>
            </div>
        </section>

        <!-- Lightbox -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="lightboxOpen" class="fixed inset-0 z-[999] flex flex-col bg-black/95" @touchstart="onTouchStart" @touchend="onTouchEnd">
                    <!-- 頂部 -->
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-stone-400">{{ lightboxIndex + 1 }} / {{ album.gallery.length }}</span>
                        <button @click="closeLightbox" class="flex h-10 w-10 items-center justify-center rounded-full text-stone-400 transition hover:bg-white/10 hover:text-white">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- 主圖 -->
                    <div class="relative flex flex-1 items-center justify-center px-4">
                        <button v-if="lightboxIndex > 0" @click="prev" class="absolute left-4 z-10 hidden h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 md:flex">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                        </button>
                        <img :src="mediaUrl(album.gallery[lightboxIndex])" :alt="`施工照片 ${lightboxIndex + 1}`" class="max-h-[75vh] max-w-full object-contain" :key="lightboxIndex" />
                        <button v-if="lightboxIndex < album.gallery.length - 1" @click="next" class="absolute right-4 z-10 hidden h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 md:flex">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                        </button>
                    </div>

                    <!-- 縮圖列 -->
                    <div class="flex justify-center gap-2 overflow-x-auto px-6 py-4">
                        <button
                            v-for="(img, i) in album.gallery"
                            :key="i"
                            @click="lightboxIndex = i"
                            class="h-14 w-14 shrink-0 overflow-hidden rounded border-2 transition md:h-16 md:w-16"
                            :class="i === lightboxIndex ? 'border-[#c0965c]' : 'border-transparent opacity-50 hover:opacity-80'"
                        >
                            <img :src="mediaUrl(img)" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </SiteLayout>
</template>
