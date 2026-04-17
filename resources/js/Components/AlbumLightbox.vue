<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useMedia } from '@/composables/useMedia';

const { mediaUrl } = useMedia();

const props = defineProps({
    album: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const currentIndex = ref(0);
const touchStartX = ref(0);

const images = ref([]);

watch(() => props.album, (val) => {
    if (val?.gallery?.length) {
        images.value = val.gallery;
        currentIndex.value = 0;
    }
}, { immediate: true });

function prev() {
    if (currentIndex.value > 0) currentIndex.value--;
}

function next() {
    if (currentIndex.value < images.value.length - 1) currentIndex.value++;
}

function goTo(index) {
    currentIndex.value = index;
}

function onKeydown(e) {
    if (!props.album) return;
    if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'Escape') emit('close');
}

function onTouchStart(e) {
    touchStartX.value = e.touches[0].clientX;
}

function onTouchEnd(e) {
    const diff = e.changedTouches[0].clientX - touchStartX.value;
    if (Math.abs(diff) > 50) {
        diff > 0 ? prev() : next();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="album" class="fixed inset-0 z-[999] flex flex-col bg-black/92" @touchstart="onTouchStart" @touchend="onTouchEnd">
                <!-- 頂部列 -->
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="text-sm text-stone-400">
                        <span class="text-[#c0965c] font-medium">工程進度：{{ album.progress_percent }}%</span>
                        <span class="mx-3 text-stone-600">|</span>
                        <span>{{ currentIndex + 1 }} / {{ images.length }}</span>
                    </div>
                    <button @click="emit('close')" class="flex h-10 w-10 items-center justify-center rounded-full text-stone-400 transition hover:bg-white/10 hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- 主圖區 -->
                <div class="relative flex flex-1 items-center justify-center px-4">
                    <!-- 左箭頭 -->
                    <button v-if="currentIndex > 0" @click="prev" class="absolute left-4 z-10 hidden md:flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                    </button>

                    <!-- 圖片 -->
                    <img
                        :src="mediaUrl(images[currentIndex])"
                        :alt="`施工照片 ${currentIndex + 1}`"
                        class="max-h-[70vh] max-w-full object-contain transition-opacity duration-300"
                        :key="currentIndex"
                    />

                    <!-- 右箭頭 -->
                    <button v-if="currentIndex < images.length - 1" @click="next" class="absolute right-4 z-10 hidden md:flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </button>
                </div>

                <!-- 底部縮圖列 -->
                <div class="flex justify-center gap-2 overflow-x-auto px-6 py-4">
                    <button
                        v-for="(img, i) in images"
                        :key="i"
                        @click="goTo(i)"
                        class="h-16 w-16 shrink-0 overflow-hidden rounded border-2 transition md:h-20 md:w-20"
                        :class="i === currentIndex ? 'border-[#c0965c]' : 'border-transparent opacity-50 hover:opacity-80'"
                    >
                        <img :src="mediaUrl(img)" :alt="`縮圖 ${i + 1}`" class="h-full w-full object-cover" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
