<script setup>
import { computed } from 'vue';
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
    title: props.page?.seo_title || props.page?.title || '關於我們',
    description: props.metaDescription || props.page?.seo_description || props.page?.summary || '',
    image: props.page?.cover_image_path,
    breadcrumbs: [
        { name: '首頁', url: '/' },
        { name: '關於我們' },
    ],
    faqItems: props.page?.faq_items,
});

const teamMembers = computed(() => props.site.about_content?.team_members || []);
const isClassic = computed(() => props.site.theme_key !== 'builder-editorial');
</script>

<template>
    <SiteLayout :title="page?.title || '關於我們'" :site="site" :navigation="navigation" :route-map="routeMap" :is-preview="isPreview" :seo="seo">
        <section class="mx-auto max-w-5xl px-6 py-20">
            <h1 class="text-3xl font-bold md:text-4xl">{{ page?.summary }}</h1>
            <div class="mt-12 prose max-w-none prose-stone prose-p:leading-8 prose-p:text-stone-800" v-html="page?.content" />
        </section>


        <!-- 形象照 2×2 Grid -->
        <section v-if="page?.gallery?.length" class="mx-auto max-w-5xl px-6 pb-16">
            <div class="grid gap-4 md:grid-cols-2" data-animate>
                <div
                    v-for="(img, i) in page.gallery.slice(0, 4)"
                    :key="i"
                    class="h-64 rounded-lg bg-cover bg-center bg-stone-100"
                    :style="{ backgroundImage: `url('${mediaUrl(img)}')` }"
                ></div>
            </div>
        </section>

        <!-- Team Section（classic theme） -->
        <section v-if="isClassic && teamMembers.length" class="py-20 px-6 bg-[#f8f8f8]">
            <div class="mx-auto max-w-[1200px]">
                <div class="text-center">
                    <p class="text-sm uppercase tracking-[0.2em] text-stone-500">經營團隊</p>
                    <h2 class="mt-2 text-3xl font-semibold text-[#333]">經營團隊</h2>
                </div>
                <div class="mx-auto mt-12 max-w-[800px] grid gap-10 md:grid-cols-2">
                    <article v-for="member in teamMembers" :key="member.name" class="text-center" data-animate>
                        <div class="mx-auto mb-6 h-48 w-48 overflow-hidden rounded-full bg-stone-200"
                             :style="member.photo ? { backgroundImage: `url('${mediaUrl(member.photo)}')`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}">
                        </div>
                        <p v-if="member.company" class="text-xs uppercase tracking-[0.3em] text-stone-500 font-serif">{{ member.company }}</p>
                        <h3 class="mt-2 text-xl font-serif text-[#333]">{{ member.name }} {{ member.title }}</h3>
                        <p class="mt-3 text-sm leading-7 text-stone-600">{{ member.description }}</p>
                    </article>
                </div>
            </div>
        </section>

        <FaqSection v-if="page?.faq_items?.length" :items="page.faq_items" />
    </SiteLayout>
</template>
