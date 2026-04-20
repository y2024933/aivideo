<script setup>
import { useMedia } from '@/composables/useMedia';
import { computed } from 'vue';

const props = defineProps({
    src: String,
    alt: { type: String, default: '' },
});

const { mediaUrl } = useMedia();

const originalSrc = computed(() => mediaUrl(props.src));
const webpSrc = computed(() => {
    if (!props.src) return null;
    const webp = props.src.replace(/\.(jpg|jpeg|png|gif)$/i, '.webp');
    return webp !== props.src ? mediaUrl(webp) : null;
});
</script>

<template>
    <picture v-if="src">
        <source v-if="webpSrc" :srcset="webpSrc" type="image/webp" />
        <img :src="originalSrc" :alt="alt" v-bind="$attrs" />
    </picture>
</template>
