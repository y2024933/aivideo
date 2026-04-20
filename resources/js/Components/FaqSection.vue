<script setup>
import { ref } from 'vue';

const props = defineProps({ items: { type: Array, required: true } });
const openIndex = ref(null);
const toggle = (i) => { openIndex.value = openIndex.value === i ? null : i; };
</script>

<template>
    <section class="mx-auto max-w-4xl px-6 py-16">
        <h2 class="mb-8 text-3xl font-semibold">常見問題</h2>
        <div class="divide-y divide-stone-200">
            <div v-for="(item, i) in items" :key="i" class="py-4">
                <button @click="toggle(i)" class="flex w-full items-center justify-between text-left text-lg font-medium transition hover:text-[var(--site-primary)]">
                    {{ item.question }}
                    <span class="ml-4 shrink-0 transition-transform" :class="{ 'rotate-180': openIndex === i }">&#9660;</span>
                </button>
                <div v-show="openIndex === i" class="mt-3 text-stone-600 leading-relaxed">{{ item.answer }}</div>
            </div>
        </div>
    </section>
</template>
