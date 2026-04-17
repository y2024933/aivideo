<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useMedia } from '@/composables/useMedia';

const { mediaUrl } = useMedia();

const props = defineProps({
    projects: { type: Array, default: () => [] },
});

const currentSlide = ref(0);
const slidesEl = ref(null);
const total = computed(() => props.projects.length);
const isDragging = ref(false);
let dragStartX = 0;
let autoplayTimer = null;

function normalizeIndex() {
    currentSlide.value = ((currentSlide.value % total.value) + total.value) % total.value;
}
function prev() { currentSlide.value--; normalizeIndex(); resetAutoplay(); }
function next() { currentSlide.value++; normalizeIndex(); resetAutoplay(); }

const slideTransform = computed(() => `translateX(-${currentSlide.value * 100}%)`);

function startAutoplay() { autoplayTimer = setInterval(() => { currentSlide.value++; normalizeIndex(); }, 4000); }
function resetAutoplay() { clearInterval(autoplayTimer); startAutoplay(); }

/* Touch */
function onTouchStart(e) { dragStartX = e.changedTouches[0].screenX; }
function onTouchEnd(e) {
    const diff = dragStartX - e.changedTouches[0].screenX;
    if (Math.abs(diff) > 50) { diff > 0 ? next() : prev(); }
}

/* Mouse drag */
function onMouseDown(e) { isDragging.value = true; dragStartX = e.clientX; }
function onMouseMove(e) { if (isDragging.value) e.preventDefault(); }
function onMouseUp(e) {
    if (!isDragging.value) return;
    isDragging.value = false;
    const diff = dragStartX - e.clientX;
    if (Math.abs(diff) > 60) { diff > 0 ? next() : prev(); }
}

onMounted(() => {
    startAutoplay();
    document.addEventListener('mouseup', onMouseUp);
});
onUnmounted(() => {
    clearInterval(autoplayTimer);
    document.removeEventListener('mouseup', onMouseUp);
});
</script>

<template>
    <div class="classic-slider">
        <button class="classic-nav-btn classic-nav-prev" aria-label="上一個" @click="prev">&#8592;</button>
        <button class="classic-nav-btn classic-nav-next" aria-label="下一個" @click="next">&#8594;</button>
        <div
            ref="slidesEl"
            class="classic-slides"
            :style="{ transform: slideTransform }"
            @touchstart.passive="onTouchStart"
            @touchend="onTouchEnd"
            @mousedown="onMouseDown"
            @mousemove="onMouseMove"
            @dragstart.prevent
        >
            <div
                v-for="project in projects"
                :key="project.id"
                class="classic-slide"
                :style="{ backgroundImage: project.featured_image_path
                    ? `linear-gradient(rgba(0,0,0,0.2),rgba(0,0,0,0.2)),url('${mediaUrl(project.featured_image_path)}')`
                    : `linear-gradient(135deg, rgba(69,123,157,0.4), rgba(30,107,155,0.6))` }"
            >
                <div class="classic-slide-overlay"></div>
                <div class="classic-slide-info">
                    <h3>{{ project.name }}</h3>
                    <span>{{ project.launch_year }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
