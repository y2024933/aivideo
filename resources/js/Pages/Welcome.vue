<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    site: { type: Object, required: true },
    newsArticles: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    progressUpdates: { type: Array, default: () => [] },
    canLogin: { type: String, required: true },
    previewUrl: { type: String, required: true },
});

const isEditorial = computed(() => props.site.theme_key === 'builder-editorial');
const sections = ['hero', 'projects', 'news', 'progress', 'contact'];

function mediaUrl(path) {
    if (!path) {
        return null;
    }

    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    return `/storage/${path}`;
}

const heroBackgroundStyle = computed(() => {
    const image = props.site.hero_content?.background_image;

    if (!image) {
        return null;
    }

    return {
        backgroundImage: `linear-gradient(rgba(15, 23, 42, 0.6), rgba(15, 23, 42, 0.6)), url('${mediaUrl(image)}')`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
    };
});

const accentStyle = computed(() => ({
    '--site-primary': isEditorial.value ? '#8d5b34' : '#184c61',
    '--site-secondary': isEditorial.value ? '#efe4d7' : '#b59a6a',
}));
</script>

<template>
    <Head :title="site.seo_defaults?.title || site.name" />

    <div class="min-h-screen" :class="isEditorial ? 'bg-[#f5efe8] text-stone-900' : 'bg-stone-950 text-stone-100'" :style="accentStyle">
        <header :class="isEditorial ? 'border-b border-stone-300 bg-white/80 backdrop-blur' : 'border-b border-white/10'"">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                <div class="flex items-center gap-4">
                    <img v-if="site.logo_path" :src="mediaUrl(site.logo_path)" :alt="site.name" class="h-12 w-12 rounded-full object-cover" />
                    <div>
                        <p :class="isEditorial ? 'text-xs uppercase tracking-[0.35em] text-stone-500' : 'text-xs uppercase tracking-[0.35em] text-stone-400'">
                            {{ site.brand_name || site.name }}
                        </p>
                        <h1 class="mt-2 text-2xl font-semibold">{{ site.name }}</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a :href="previewUrl" :class="isEditorial ? 'rounded-full border border-stone-300 px-4 py-2 text-sm text-stone-700 transition hover:border-[var(--site-primary)] hover:text-[var(--site-primary)]' : 'rounded-full border border-white/20 px-4 py-2 text-sm text-stone-100 transition hover:border-[var(--site-secondary)] hover:text-[var(--site-secondary)]'">
                        預覽連結
                    </a>
                    <Link :href="canLogin" :class="isEditorial ? 'rounded-full bg-[var(--site-primary)] px-4 py-2 text-sm text-white transition hover:opacity-90' : 'rounded-full border border-white/20 px-4 py-2 text-sm text-stone-100 transition hover:border-[var(--site-secondary)] hover:text-[var(--site-secondary)]'">
                        後台登入
                    </Link>
                </div>
            </div>
        </header>

        <main>
            <section
                v-if="sections.includes('hero')"
                class="border-b"
                :class="isEditorial ? 'border-stone-300' : 'border-white/10'"
                :style="heroBackgroundStyle"
            >
                <div class="mx-auto grid max-w-6xl gap-12 px-6 py-20 lg:grid-cols-[1.2fr_0.8fr]">
                    <div>
                        <p :class="isEditorial ? 'text-sm uppercase tracking-[0.35em] text-[var(--site-primary)]' : 'text-sm uppercase tracking-[0.35em] text-[var(--site-secondary)]'">
                            {{ site.hero_content?.eyebrow || site.brand_name || site.name }}
                        </p>
                        <h2 class="mt-6 max-w-3xl text-5xl font-semibold leading-tight" :class="isEditorial ? 'text-stone-900' : 'text-white'">
                            {{ site.hero_content?.headline || '多網站共用後台的建設品牌平台' }}
                        </h2>
                        <p class="mt-6 max-w-2xl text-lg leading-8" :class="isEditorial ? 'text-stone-600' : 'text-stone-300'">
                            {{ site.hero_content?.subheadline }}
                        </p>
                        <a
                            v-if="site.hero_content?.cta_label"
                            :href="site.hero_content?.cta_link || '#'"
                            class="mt-8 inline-flex rounded-full px-5 py-3 text-sm font-medium transition"
                            :class="isEditorial ? 'bg-[var(--site-primary)] text-white hover:opacity-90' : 'bg-[var(--site-secondary)] text-stone-950 hover:opacity-90'"
                        >
                            {{ site.hero_content?.cta_label }}
                        </a>
                    </div>
                    <div
                        class="rounded-[2rem] p-8 backdrop-blur"
                        :class="isEditorial ? 'border border-stone-300 bg-white/70' : 'border border-white/10 bg-white/5'"
                    >
                        <p :class="isEditorial ? 'text-sm uppercase tracking-[0.35em] text-stone-500' : 'text-sm uppercase tracking-[0.35em] text-stone-400'">站台設定</p>
                        <dl class="mt-6 space-y-4 text-sm" :class="isEditorial ? 'text-stone-700' : 'text-stone-300'">
                            <div class="flex justify-between gap-6 border-b pb-4" :class="isEditorial ? 'border-stone-200' : 'border-white/10'">
                                <dt>主網域</dt>
                                <dd>{{ site.domains?.[0]?.domain || '本機預覽' }}</dd>
                            </div>
                            <div class="flex justify-between gap-6 border-b pb-4" :class="isEditorial ? 'border-stone-200' : 'border-white/10'">
                                <dt>主題</dt>
                                <dd>{{ site.theme_key }}</dd>
                            </div>
                            <div class="flex justify-between gap-6 border-b pb-4" :class="isEditorial ? 'border-stone-200' : 'border-white/10'">
                                <dt>聯絡信箱</dt>
                                <dd>{{ site.footer_content?.email || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-6">
                                <dt>聯絡電話</dt>
                                <dd>{{ site.footer_content?.phone || '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <section v-if="sections.includes('projects')" class="mx-auto max-w-6xl px-6 py-16">
                <div class="flex items-end justify-between gap-6">
                    <div>
                        <p :class="isEditorial ? 'text-sm uppercase tracking-[0.35em] text-stone-500' : 'text-sm uppercase tracking-[0.35em] text-stone-500'">建案作品</p>
                        <h3 class="mt-3 text-3xl font-semibold" :class="isEditorial ? 'text-stone-900' : 'text-white'">建案管理</h3>
                    </div>
                    <p class="max-w-xl text-sm leading-7" :class="isEditorial ? 'text-stone-600' : 'text-stone-400'">
                        後台同一套，但每個建案都掛在不同 `site_id`。這樣可以共用模組，又能讓兩個前台站維持不同品牌風格。
                    </p>
                </div>
                <div class="mt-8 grid gap-6 md:grid-cols-3">
                    <article
                        v-for="project in projects"
                        :key="project.id"
                        class="overflow-hidden rounded-[1.5rem] border"
                        :class="isEditorial ? 'border-stone-300 bg-white shadow-sm' : 'border-white/10 bg-white/5'"
                    >
                        <div
                            v-if="project.featured_image_path"
                            class="h-56 w-full bg-cover bg-center"
                            :style="{ backgroundImage: `url('${mediaUrl(project.featured_image_path)}')` }"
                        />
                        <div class="p-6">
                            <p class="text-xs uppercase tracking-[0.3em]" :class="isEditorial ? 'text-[var(--site-primary)]' : 'text-[var(--site-secondary)]'">
                                {{ project.project_status?.name }}
                            </p>
                            <h4 class="mt-4 text-2xl font-semibold" :class="isEditorial ? 'text-stone-900' : 'text-white'">{{ project.name }}</h4>
                            <p class="mt-3 text-sm" :class="isEditorial ? 'text-stone-600' : 'text-stone-400'">{{ project.summary }}</p>
                            <p class="mt-6 text-xs uppercase tracking-[0.3em]" :class="isEditorial ? 'text-stone-400' : 'text-stone-500'">
                                {{ project.location }} / {{ project.area }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>

            <section
                v-if="sections.includes('news') || sections.includes('progress')"
                class="border-y"
                :class="isEditorial ? 'border-stone-300 bg-white/60' : 'border-white/10 bg-white/[0.03]'"
            >
                <div class="mx-auto grid max-w-6xl gap-10 px-6 py-16 lg:grid-cols-2">
                    <div v-if="sections.includes('news')">
                        <p class="text-sm uppercase tracking-[0.35em]" :class="isEditorial ? 'text-stone-500' : 'text-stone-500'">最新消息</p>
                        <h3 class="mt-3 text-3xl font-semibold" :class="isEditorial ? 'text-stone-900' : 'text-white'">最新消息</h3>
                        <div class="mt-8 space-y-6">
                            <article v-for="article in newsArticles" :key="article.id" class="border-b pb-6 last:border-b-0" :class="isEditorial ? 'border-stone-200' : 'border-white/10'">
                                <div class="flex gap-4">
                                    <div
                                        v-if="article.featured_image_path"
                                        class="h-24 w-24 shrink-0 rounded-2xl bg-cover bg-center"
                                        :style="{ backgroundImage: `url('${mediaUrl(article.featured_image_path)}')` }"
                                    />
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.3em]" :class="isEditorial ? 'text-[var(--site-primary)]' : 'text-[var(--site-secondary)]'">
                                            {{ article.category || '最新消息' }}
                                        </p>
                                        <h4 class="mt-3 text-xl font-semibold" :class="isEditorial ? 'text-stone-900' : 'text-white'">{{ article.title }}</h4>
                                        <p class="mt-2 text-sm leading-7" :class="isEditorial ? 'text-stone-600' : 'text-stone-400'">{{ article.summary }}</p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div v-if="sections.includes('progress')">
                        <p class="text-sm uppercase tracking-[0.35em]" :class="isEditorial ? 'text-stone-500' : 'text-stone-500'">工程進度</p>
                        <h3 class="mt-3 text-3xl font-semibold" :class="isEditorial ? 'text-stone-900' : 'text-white'">工程進度</h3>
                        <div class="mt-8 space-y-5">
                            <article
                                v-for="item in progressUpdates"
                                :key="item.id"
                                class="rounded-[1.5rem] border p-6"
                                :class="isEditorial ? 'border-stone-300 bg-[#fbf7f2]' : 'border-white/10 bg-stone-900/80'"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="text-xl font-semibold" :class="isEditorial ? 'text-stone-900' : 'text-white'">{{ item.title }}</h4>
                                        <p class="mt-2 text-sm" :class="isEditorial ? 'text-stone-600' : 'text-stone-400'">{{ item.summary }}</p>
                                    </div>
                                    <span class="text-2xl font-semibold" :class="isEditorial ? 'text-[var(--site-primary)]' : 'text-[var(--site-secondary)]'">{{ item.progress_percent }}%</span>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="sections.includes('contact')" class="mx-auto max-w-6xl px-6 py-16">
                <div
                    class="rounded-[2rem] border p-8"
                    :class="isEditorial ? 'border-stone-300 bg-gradient-to-r from-[var(--site-secondary)] to-white' : 'border-white/10 bg-gradient-to-r from-[color:var(--site-secondary)]/10 to-transparent'"
                >
                    <p class="text-sm uppercase tracking-[0.35em]" :class="isEditorial ? 'text-stone-500' : 'text-stone-500'">聯絡我們</p>
                    <h3 class="mt-3 text-3xl font-semibold" :class="isEditorial ? 'text-stone-900' : 'text-white'">聯絡我們訊息</h3>
                    <p class="mt-4 max-w-3xl text-sm leading-7" :class="isEditorial ? 'text-stone-700' : 'text-stone-300'">
                        聯絡表單會寫入共用後台的 `contact_messages`。你可以用一個後台同時管理兩個前台站，並且由編輯器新增文章、建案圖片與工程進度。
                    </p>
                    <div class="mt-8 grid gap-6 md:grid-cols-3">
                        <div class="rounded-2xl p-5" :class="isEditorial ? 'bg-white/80' : 'bg-white/5'">
                            <p class="text-sm" :class="isEditorial ? 'text-stone-500' : 'text-stone-400'">Email</p>
                            <p class="mt-2 font-semibold">{{ site.footer_content?.email || '-' }}</p>
                        </div>
                        <div class="rounded-2xl p-5" :class="isEditorial ? 'bg-white/80' : 'bg-white/5'">
                            <p class="text-sm" :class="isEditorial ? 'text-stone-500' : 'text-stone-400'">電話</p>
                            <p class="mt-2 font-semibold">{{ site.footer_content?.phone || '-' }}</p>
                        </div>
                        <div class="rounded-2xl p-5" :class="isEditorial ? 'bg-white/80' : 'bg-white/5'">
                            <p class="text-sm" :class="isEditorial ? 'text-stone-500' : 'text-stone-400'">地址</p>
                            <p class="mt-2 font-semibold">{{ site.footer_content?.address || '-' }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
