<script setup>
defineProps({
    en: { type: String, required: true },
    zh1: { type: String, required: true },
    zh2: { type: String, required: true },
    light: { type: Boolean, default: false },
});
</script>

<template>
    <div class="section-title" :class="{ 'section-light': light }" data-animate>
        <span class="section-title-en">{{ en }}</span>
        <div class="section-title-zh">
            <span>{{ zh1 }}</span>
            <i class="title-slash"></i>
            <span>{{ zh2 }}</span>
        </div>
    </div>
</template>
