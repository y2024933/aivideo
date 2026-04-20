import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useMedia } from './useMedia';

/**
 * SEO composable：組裝 OG meta、Twitter Card、JSON-LD 結構化資料
 *
 * @param {Object} options - { title, description, image, type, url, jsonLd, breadcrumbs }
 * @returns {{ meta: ComputedRef, jsonLdScript: ComputedRef }}
 */
export function useSeo(options = {}) {
    const page = usePage();
    const { mediaUrl } = useMedia();

    const site = computed(() => page.props.site);
    const baseUrl = computed(() => page.props.baseUrl || '');
    const currentUrl = computed(() => page.props.currentUrl || '');

    /** 將相對路徑轉為絕對 URL */
    function absoluteUrl(path) {
        if (!path) return null;
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        const media = mediaUrl(path);
        return media ? `${baseUrl.value}${media.startsWith('/') ? '' : '/'}${media}` : null;
    }

    const meta = computed(() => {
        const s = site.value;
        const title = options.title || s?.seo_defaults?.title || s?.name || '';
        const description = options.description || s?.seo_defaults?.description || '';
        const image = absoluteUrl(options.image || s?.logo_path) || '';
        const url = options.url || currentUrl.value;
        const type = options.type || 'website';

        return { title, description, image, url, type, siteName: s?.name || '' };
    });

    const jsonLdScript = computed(() => {
        const s = site.value;
        if (!s) return '';

        const graph = [];

        // Organization
        const org = { '@type': 'Organization', name: s.name };
        const logo = absoluteUrl(s.logo_path);
        if (logo) org.logo = logo;
        if (s.footer_content?.phone) org.telephone = s.footer_content.phone;
        if (s.footer_content?.email) org.email = s.footer_content.email;
        if (s.footer_content?.address) org.address = s.footer_content.address;
        const sameAs = Object.values(s.social_links || {}).filter(Boolean);
        if (sameAs.length) org.sameAs = sameAs;
        if (baseUrl.value) org.url = baseUrl.value;
        graph.push(org);

        // BreadcrumbList
        if (options.breadcrumbs?.length) {
            graph.push({
                '@type': 'BreadcrumbList',
                itemListElement: options.breadcrumbs.map((item, i) => ({
                    '@type': 'ListItem',
                    position: i + 1,
                    name: item.name,
                    ...(item.url ? { item: `${baseUrl.value}${item.url}` } : {}),
                })),
            });
        }

        // 頁面特定 JSON-LD
        if (options.jsonLd) {
            const custom = { ...options.jsonLd };
            // 處理 image 欄位轉絕對路徑
            if (custom.image) custom.image = absoluteUrl(custom.image);
            if (custom.logo && typeof custom.logo === 'string') custom.logo = absoluteUrl(custom.logo);
            // publisher 內的 logo
            if (custom.publisher?.logo) custom.publisher.logo = absoluteUrl(custom.publisher.logo);
            graph.push(custom);
        }

        // FAQPage 結構化資料
        if (options.faqItems?.length) {
            graph.push({
                '@type': 'FAQPage',
                mainEntity: options.faqItems.map(item => ({
                    '@type': 'Question',
                    name: item.question,
                    acceptedAnswer: { '@type': 'Answer', text: item.answer },
                })),
            });
        }

        return JSON.stringify({ '@context': 'https://schema.org', '@graph': graph });
    });

    /** 給 SiteLayout :seo prop 用的打包物件（已解包 computed） */
    const seo = computed(() => ({ meta: meta.value, jsonLdScript: jsonLdScript.value }));

    return { meta, jsonLdScript, seo, absoluteUrl };
}
