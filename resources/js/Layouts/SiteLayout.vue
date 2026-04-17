<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, nextTick, onMounted, onUnmounted } from 'vue';
import { useMedia } from '@/composables/useMedia';

const { mediaUrl } = useMedia();

const props = defineProps({
    title: { type: String, required: true },
    site: { type: Object, required: true },
    navigation: { type: Object, default: () => ({ primary: [], secondary: [], footer: [] }) },
    routeMap: { type: Object, required: true },
    isPreview: { type: Boolean, default: false },
});

const isEditorial = computed(() => props.site.theme_key === 'builder-editorial');
const isClassic = computed(() => !isEditorial.value);
const themeStyle = computed(() => ({
    '--site-primary': props.site.primary_color || (isEditorial.value ? '#8d5b34' : '#457b9d'),
    '--site-secondary': props.site.secondary_color || (isEditorial.value ? '#efe4d7' : '#b59a6a'),
}));

/* Classic theme — 漢堡選單 */
const menuOpen = ref(false);
function toggleMenu() { menuOpen.value = !menuOpen.value; }
function closeMenu() { menuOpen.value = false; }

/* Classic theme — Header 滾動背景 */
const headerScrolled = ref(false);
function onScroll() { headerScrolled.value = window.scrollY > 60; }
onMounted(() => { if (isClassic.value) window.addEventListener('scroll', onScroll, { passive: true }); });
onUnmounted(() => { window.removeEventListener('scroll', onScroll); });

/* Classic theme — Scroll Animation */
let animationObserver = null;
onMounted(() => {
    if (!isClassic.value) return;
    animationObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                animationObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });
    /* nextTick 確保 slot 子元件已渲染 */
    nextTick(() => {
        document.querySelectorAll('[data-animate]').forEach(el => animationObserver.observe(el));
    });
});
onUnmounted(() => { animationObserver?.disconnect(); });

/* 追蹤碼注入 */
onMounted(() => {
    const tracking = props.site.setting?.tracking;
    if (!tracking) return;

    // GA4
    if (tracking.ga4_id) {
        const s = document.createElement('script');
        s.async = true;
        s.src = `https://www.googletagmanager.com/gtag/js?id=${tracking.ga4_id}`;
        document.head.appendChild(s);
        window.dataLayer = window.dataLayer || [];
        window.gtag = function(){ window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', tracking.ga4_id);
    }

    // GTM
    if (tracking.gtm_id) {
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',tracking.gtm_id);
    }

    // Meta Pixel
    if (tracking.meta_pixel_id) {
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
        window.fbq('init', tracking.meta_pixel_id);
        window.fbq('track', 'PageView');
    }

    // LINE Tag
    if (tracking.line_tag_id) {
        (function(g,d,o){g._ltq=g._ltq||[];g._lt=g._lt||function(){g._ltq.push(arguments)};var h=d.getElementsByTagName(o)[0];var j=d.createElement(o);j.async=1;j.src='https://d.line-scdn.net/n/line_tag/public/release/v1/lt.js';h.parentNode.insertBefore(j,h)})(window,document,'script');
        window._lt('init',{customerType:'lap',tagId:tracking.line_tag_id});
        window._lt('send','pv',[tracking.line_tag_id]);
    }
});

</script>

<template>
    <Head :title="title" />

    <div
        :class="[
            isEditorial ? 'bg-[#f7f0e8] text-stone-900' : 'theme-classic bg-white text-[#333]',
            { 'menu-open': menuOpen }
        ]"
        :style="themeStyle"
        class="flex min-h-screen flex-col"
    >
        <!-- ===== Editorial Header（保持不動） ===== -->
        <header v-if="isEditorial" class="border-b border-stone-300 bg-white/85 backdrop-blur">
            <div class="mx-auto max-w-7xl px-6 py-5">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-center gap-4">
                        <img v-if="site.logo_path" :src="mediaUrl(site.logo_path)" :alt="site.name" class="h-14 w-14 rounded-full object-cover" />
                        <div>
                            <div class="flex items-center gap-3">
                                <p class="text-xs uppercase tracking-[0.35em] text-stone-500">{{ site.brand_name || site.name }}</p>
                                <span v-if="isPreview" class="rounded-full bg-[var(--site-secondary)] px-3 py-1 text-[11px] font-medium uppercase tracking-[0.25em] text-stone-700">預覽模式</span>
                            </div>
                            <a :href="routeMap.home" class="mt-2 block text-3xl font-semibold">{{ site.name }}</a>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a :href="routeMap.preview" class="rounded-full border border-stone-300 px-4 py-2 text-sm text-stone-700 transition hover:border-[var(--site-primary)] hover:text-[var(--site-primary)]">站台預覽</a>
                        <a :href="routeMap.login" class="rounded-full bg-[var(--site-primary)] px-4 py-2 text-sm text-white transition hover:opacity-90">後台登入</a>
                    </div>
                </div>
                <nav class="mt-8 flex flex-wrap gap-x-8 gap-y-3 text-base">
                    <a v-for="item in navigation.primary" :key="`primary-${item.label}`" :href="item.url" class="text-stone-700 transition hover:text-[var(--site-primary)]">{{ item.label }}</a>
                </nav>
                <nav v-if="navigation.secondary?.length" class="mt-5 flex flex-wrap gap-x-8 gap-y-3 border-t border-stone-200 pt-5 text-sm text-stone-600">
                    <template v-for="item in navigation.secondary" :key="`secondary-${item.label}`">
                        <a :href="item.url" class="transition hover:text-[var(--site-primary)]">{{ item.label }}</a>
                        <template v-if="item.children?.length">
                            <a v-for="child in item.children" :key="`child-${child.label}`" :href="child.url" class="transition hover:text-[var(--site-primary)]">{{ child.label }}</a>
                        </template>
                    </template>
                </nav>
            </div>
        </header>

        <!-- ===== Classic Header — 白底兩層式 ===== -->
        <template v-else>
            <header class="site-header" :class="{ 'header-scrolled': headerScrolled }">
                <div class="header-primary">
                    <a class="header-logo" :href="routeMap.home">
                        <div>
                            <span class="header-logo-text">{{ site.name }}</span>
                            <span class="header-logo-sub">{{ site.brand_name }}</span>
                        </div>
                    </a>

                    <nav class="header-nav">
                        <a class="header-nav-item" :href="routeMap.about">關於我們</a>
                        <div class="header-nav-item">
                            <a :href="routeMap.projects">建築作品</a>
                            <div class="nav-dropdown">
                                <a :href="`${routeMap.projects}?status=selling`">熱銷新案</a>
                                <a :href="`${routeMap.projects}?status=completed`">歷史建案</a>
                            </div>
                        </div>
                        <a class="header-nav-item" :href="routeMap.news">最新消息</a>
                        <div class="header-nav-item">
                            <a :href="routeMap.services">多元服務</a>
                            <div class="nav-dropdown">
                                <a :href="routeMap.services">不動產</a>
                                <a :href="routeMap.services">代租代管</a>
                            </div>
                        </div>
                        <a class="header-nav-item" :href="routeMap.progress">工程進度</a>
                        <a class="header-nav-item" :href="routeMap.contact">聯絡我們</a>
                    </nav>

                    <div class="header-social">
                        <a v-if="site.setting?.social_links?.facebook" :href="site.setting.social_links.facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook">FB</a>
                        <a v-if="site.setting?.social_links?.instagram" :href="site.setting.social_links.instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram">IG</a>
                        <a v-if="site.setting?.social_links?.line" :href="site.setting.social_links.line" target="_blank" rel="noopener noreferrer" aria-label="LINE">LINE</a>
                    </div>

                    <button class="mobile-trigger" type="button" :aria-expanded="menuOpen" aria-label="開啟選單" @click="toggleMenu">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </header>

            <!-- 手機版 Side Panel（右滑出） -->
            <div class="side-panel-overlay" @click="closeMenu"></div>
            <nav class="side-panel">
                <a :href="routeMap.about" @click="closeMenu">關於我們</a>
                <a :href="`${routeMap.projects}?status=selling`" @click="closeMenu">熱銷新案</a>
                <a :href="`${routeMap.projects}?status=completed`" @click="closeMenu">歷史建案</a>
                <a :href="routeMap.news" @click="closeMenu">最新消息</a>
                <a :href="routeMap.services" @click="closeMenu">多元服務</a>
                <a :href="routeMap.progress" @click="closeMenu">工程進度</a>
                <a :href="routeMap.contact" @click="closeMenu">聯絡我們</a>
            </nav>
        </template>

        <main class="flex-1">
            <slot />
        </main>

        <!-- ===== Editorial Footer（保持不動） ===== -->
        <footer v-if="isEditorial" class="border-t border-stone-300 bg-white/70">
            <div class="mx-auto grid max-w-7xl gap-10 px-6 py-12 lg:grid-cols-[1.3fr_0.7fr]">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-stone-500">聯絡資訊</p>
                    <h2 class="mt-4 text-2xl font-semibold">{{ site.name }}</h2>
                    <div class="mt-6 space-y-2 text-sm text-stone-600">
                        <p>{{ site.setting?.footer_content?.address || '地址待補' }}</p>
                        <p>{{ site.setting?.footer_content?.phone || site.contact_phone || '-' }}</p>
                        <p>{{ site.setting?.footer_content?.email || site.contact_email || '-' }}</p>
                        <p>{{ site.setting?.footer_content?.copyright || `Copyright © ${site.name}` }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-stone-500">快速連結</p>
                    <div class="mt-6 grid gap-3 text-sm">
                        <a v-for="item in navigation.footer?.length ? navigation.footer : navigation.primary" :key="`footer-${item.label}`" :href="item.url" class="text-stone-600 transition hover:text-[var(--site-primary)]">{{ item.label }}</a>
                    </div>
                    <div v-if="site.setting?.social_links" class="mt-8 flex flex-wrap gap-3 text-xs uppercase tracking-[0.25em]">
                        <a v-for="(url, key) in site.setting.social_links" :key="key" v-show="url" :href="url" target="_blank" rel="noopener noreferrer" class="rounded-full border border-stone-300 px-3 py-2 text-stone-600 transition hover:border-[var(--site-primary)] hover:text-[var(--site-primary)]">{{ key }}</a>
                    </div>
                </div>
            </div>
        </footer>

        <!-- ===== Classic Footer — 深色單行式 ===== -->
        <footer v-else class="classic-footer">
            <div class="classic-footer-inner">
                <div class="footer-info">
                    <span>{{ site.setting?.footer_content?.address }}</span>
                    <span v-if="site.setting?.footer_content?.sales_phone">銷售專線：{{ site.setting.footer_content.sales_phone }}</span>
                    <span>{{ site.setting?.footer_content?.phone }}</span>
                    <a v-if="site.setting?.footer_content?.email" :href="`mailto:${site.setting.footer_content.email}`">{{ site.setting.footer_content.email }}</a>
                </div>
                <div class="footer-social">
                    <span class="text-[0.7rem] mr-1">追蹤我們</span>
                    <a v-if="site.setting?.social_links?.facebook" :href="site.setting.social_links.facebook" target="_blank" rel="noopener noreferrer" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/30 transition hover:border-white" aria-label="Facebook">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                    </a>
                    <a v-if="site.setting?.social_links?.instagram" :href="site.setting.social_links.instagram" target="_blank" rel="noopener noreferrer" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/30 transition hover:border-white" aria-label="Instagram">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                    <a v-if="site.setting?.social_links?.line" :href="site.setting.social_links.line" target="_blank" rel="noopener noreferrer" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/30 transition hover:border-white" aria-label="LINE">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
                    </a>
                </div>
                <span class="footer-copyright">{{ site.setting?.footer_content?.copyright || `Copyright © ${site.name}` }}</span>
            </div>
        </footer>

        <!-- LINE 浮動按鈕 -->
        <a
            v-if="site.setting?.tracking?.line_official_url"
            :href="site.setting.tracking.line_official_url"
            target="_blank"
            rel="noopener noreferrer"
            class="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-[#06C755] text-white shadow-lg transition hover:scale-110"
            aria-label="LINE 諮詢"
        >
            <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
        </a>

        <!-- 手機版電話浮動按鈕 -->
        <a
            v-if="site.setting?.tracking?.phone_cta"
            :href="`tel:${site.setting.tracking.phone_cta}`"
            class="fixed bottom-6 left-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-[var(--site-primary)] text-white shadow-lg transition hover:scale-110 md:hidden"
            aria-label="撥打電話"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" /></svg>
        </a>
    </div>
</template>
