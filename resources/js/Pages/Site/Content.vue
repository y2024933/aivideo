<script setup>
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
});

const { seo } = useSeo({
    title: props.page?.seo_title || props.page?.title || '',
    description: props.metaDescription || props.page?.seo_description || props.page?.summary || '',
    image: props.page?.cover_image_path,
    breadcrumbs: [
        { name: '首頁', url: '/' },
        { name: props.page?.title || '' },
    ],
    faqItems: props.page?.faq_items,
});
</script>

<template>
    <SiteLayout :title="page?.title" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview" :seo="seo">
        <section class="mx-auto max-w-5xl px-6 py-20">
            <h1 class="text-3xl font-bold md:text-4xl">{{ page?.summary || page?.title }}</h1>
            <div v-if="page?.content" class="mt-12 prose max-w-none prose-stone prose-p:leading-8 prose-p:text-stone-800" v-html="page.content" />
        </section>

        <section v-if="page?.gallery?.length" class="mx-auto max-w-5xl px-6 pb-16">
            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="(img, i) in page.gallery"
                    :key="i"
                    class="h-64 rounded-lg bg-cover bg-center bg-stone-100"
                    :style="{ backgroundImage: `url('${mediaUrl(img)}')` }"
                ></div>
            </div>
        </section>

        <FaqSection v-if="page?.faq_items?.length" :items="page.faq_items" />
    </SiteLayout>
</template>
