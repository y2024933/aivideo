<?php

namespace App\Http\Controllers;

use App\Enums\PageTemplate;
use App\Jobs\SendContactNotification;
use App\Models\ContactMessage;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Project;
use App\Models\ProgressAlbum;
use App\Models\ProgressUpdate;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function home(Request $request): Response
    {
        $site = $this->resolveSite($request);

        return Inertia::render('Site/Home', [
            ...$this->sharedProps($request, $site),
            'metaDescription' => $this->metaDescription($site->seo_defaults['description'] ?? null, null),
            'aboutPage' => $this->findPageByTemplate($site, PageTemplate::About),
            'projectsPage' => $this->findPageByTemplate($site, PageTemplate::Projects),
            'newsPage' => $this->findPageByTemplate($site, PageTemplate::News),
            'servicesPage' => $this->findPageByTemplate($site, PageTemplate::Services),
            'progressPage' => $this->findPageByTemplate($site, PageTemplate::Progress),
            'contactPage' => $this->findPageByTemplate($site, PageTemplate::Contact),
            'featuredProjects' => $this->projectsQuery($site)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->limit(3)
                ->get(),
            'latestNews' => $this->newsQuery($site)
                ->limit(3)
                ->get(),
            'latestProgress' => $this->progressQuery($site)
                ->limit(3)
                ->get(),
            'classicProjects' => $this->projectsQuery($site)
                ->whereHas('projectStatus', fn ($q) => $q->where('slug', 'completed'))
                ->orderByDesc('launch_year')
                ->get(),
        ]);
    }

    public function dynamicPage(Request $request): Response
    {
        $site = $this->resolveSite($request);
        $slug = $request->route('pageSlug');

        $page = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->where('page_type', '!=', PageTemplate::Home)
            ->firstOrFail();

        $template = $page->page_type;

        return Inertia::render($template->inertiaPage(), [
            ...$this->sharedProps($request, $site),
            'metaDescription' => $this->metaDescription($page->seo_description, $page->summary, $page->content),
            'page' => $page,
            ...$this->extraPropsForTemplate($request, $site, $template),
        ]);
    }

    public function dynamicPagePost(Request $request): RedirectResponse
    {
        $site = $this->resolveSite($request);
        $slug = $request->route('pageSlug');

        $page = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return match ($page->page_type) {
            PageTemplate::Progress => $this->progressAuth($request),
            PageTemplate::Contact => $this->submitContact($request),
            default => abort(404),
        };
    }

    public function projectShow(Request $request): Response
    {
        $site = $this->resolveSite($request);
        $slug = $request->route('slug');
        $project = Project::query()
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Site/Projects/Show', [
            ...$this->sharedProps($request, $site),
            'metaDescription' => $this->metaDescription(null, $project->summary),
            'project' => $project,
            'progressUpdates' => $project->progressUpdates()
                ->where('is_published', true)
                ->orderByDesc('reported_at')
                ->get(),
            'relatedProjects' => $this->projectsQuery($site)
                ->where('id', '!=', $project->id)
                ->orderByDesc('is_featured')
                ->orderByDesc('launch_year')
                ->limit(3)
                ->get(),
        ]);
    }

    public function newsShow(Request $request): Response
    {
        $site = $this->resolveSite($request);
        $slug = $request->route('slug');
        $article = NewsArticle::query()
            ->where('site_id', $site->id)
            ->where('is_published', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Site/News/Show', [
            ...$this->sharedProps($request, $site),
            'metaDescription' => $this->metaDescription(null, $article->summary, $article->content),
            'article' => $article,
            'relatedArticles' => $this->newsQuery($site)
                ->where('id', '!=', $article->id)
                ->limit(3)
                ->get(),
        ]);
    }

    public function progressAlbum(Request $request): Response
    {
        $site = $this->resolveSite($request);
        $album = ProgressAlbum::findOrFail($request->route('album'));

        abort_unless($album->site_id === $site->id && $album->is_published, 404);

        $authenticatedProjectId = session('progress_project_id');
        abort_unless($authenticatedProjectId && $authenticatedProjectId == $album->project_id, 403);

        $album->load('project');

        return Inertia::render('Site/ProgressAlbum', [
            ...$this->sharedProps($request, $site),
            'album' => $album,
        ]);
    }

    // ── POST handlers（由 dynamicPagePost 呼叫）─────────────

    protected function progressAuth(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'password' => 'required|string',
            'captcha' => 'required|string',
        ]);

        if (! CaptchaController::check($validated['captcha'] ?? '')) {
            return back()->withErrors(['captcha' => '驗證碼不正確，請重新輸入。'])->withInput();
        }

        $site = $this->resolveSite($request);
        $project = Project::where('site_id', $site->id)
            ->where('id', $request->project_id)
            ->firstOrFail();

        if (! $project->progress_password || $project->progress_password !== $request->password) {
            return back()->withErrors(['password' => '密碼錯誤'])->withInput();
        }

        session(['progress_project_id' => $project->id]);

        return back();
    }

    protected function submitContact(Request $request): RedirectResponse
    {
        $site = $this->resolveSite($request);

        $validated = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
            'inquiry_type' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'line_id' => ['nullable', 'string', 'max:255'],
            'contact_time' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:4000'],
            'captcha' => ['required', 'string'],
        ]);

        if (! CaptchaController::check($validated['captcha'] ?? '')) {
            return back()->withErrors(['captcha' => '驗證碼不正確，請重新輸入。'])->withInput();
        }

        unset($validated['captcha']);

        if (filled($validated['project_id'] ?? null)) {
            $projectBelongsToSite = Project::query()
                ->where('site_id', $site->id)
                ->whereKey($validated['project_id'])
                ->exists();

            abort_unless($projectBelongsToSite, 422);
        }

        $contactMessage = ContactMessage::create([
            ...$validated,
            'site_id' => $site->id,
            'status' => 'new',
            'source_page' => $request->path(),
        ]);

        SendContactNotification::dispatch($contactMessage, $site);

        return back()->with('success', '已收到您的訊息，我們會盡快與您聯繫。');
    }

    // ── 每個模板的額外 props ─────────────────────

    protected function extraPropsForTemplate(Request $request, Site $site, PageTemplate $template): array
    {
        return match ($template) {
            PageTemplate::Projects => [
                'projects' => $this->projectsQuery($site)
                    ->orderByDesc('is_featured')
                    ->orderByDesc('launch_year')
                    ->orderBy('sort_order')
                    ->get(),
                'statusList' => $site->projectStatuses()->orderBy('sort_order')->get(['id', 'name']),
            ],
            PageTemplate::News => [
                'articles' => $this->newsQuery($site)
                    ->when(request()->integer('category'), fn ($q, $cat) => $q->where('news_category_id', $cat))
                    ->paginate(12)
                    ->withQueryString(),
                'categories' => $site->newsCategories()->orderBy('sort_order')->get(['id', 'name']),
                'activeCategory' => request()->integer('category') ?: null,
            ],
            PageTemplate::Services => [
                'serviceHighlights' => filled($site->service_content['cards'] ?? [])
                    ? $site->service_content['cards']
                    : [
                        ['title' => '代租代管', 'description' => '從招租、簽約到入住後的維護與回報，建立一套穩定的管理流程。'],
                        ['title' => '售後維護', 'description' => '延續建築品質的維護節奏，將售後服務納入品牌體驗的一部分。'],
                        ['title' => '不動產顧問', 'description' => '整合建案、屋主與資產配置需求，提供更完整的長期規劃建議。'],
                    ],
                'latestNews' => $this->newsQuery($site)->limit(3)->get(),
            ],
            PageTemplate::Progress => $this->progressProps($site),
            PageTemplate::Contact => [
                'projects' => $this->projectsQuery($site)
                    ->orderByDesc('is_featured')
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug']),
                'inquiryTypes' => filled($site->contact_content['inquiry_types'] ?? [])
                    ? $site->contact_content['inquiry_types']
                    : ['預約看屋', '線上報修', '包租代管', '合作提案', '建議事項', '其他'],
            ],
            default => [],
        };
    }

    protected function progressProps(Site $site): array
    {
        $authenticatedProjectId = session('progress_project_id');
        $project = null;
        $updates = collect();
        $albums = collect();

        if ($authenticatedProjectId) {
            $project = Project::where('site_id', $site->id)->find($authenticatedProjectId);
            if ($project) {
                $updates = $project->progressUpdates()
                    ->where('is_published', true)
                    ->orderByDesc('reported_at')
                    ->with('project')
                    ->get();

                $albums = ProgressAlbum::where('site_id', $site->id)
                    ->where('project_id', $project->id)
                    ->where('is_published', true)
                    ->whereNotNull('gallery')
                    ->orderByDesc('reported_at')
                    ->get();
            }
        }

        return [
            'projects' => $this->projectsQuery($site)->get(['id', 'name', 'slug']),
            'isAuthenticated' => (bool) $project,
            'selectedProject' => $project,
            'updates' => $updates,
            'albums' => $albums,
        ];
    }

    // ── Shared ──────────────────────────────────

    protected function sharedProps(Request $request, Site $site): array
    {
        return [
            'site' => $site,
            'currentUrl' => $request->fullUrl(),
            'baseUrl' => rtrim($request->schemeAndHttpHost(), '/'),
            'navigation' => $this->navigationFor($request, $site),
            'routeMap' => $this->buildRouteMap($request, $site),
            'canonicalUrl' => $this->canonicalUrl($request, $site),
            'isPreview' => $this->isPreview($request),
        ];
    }

    protected function buildRouteMap(Request $request, Site $site): array
    {
        $base = $this->isPreview($request) ? '/preview/' . $site->slug : '';

        // 查詢該站所有已發布頁面的 page_type => slug
        $pages = Page::where('site_id', $site->id)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->get(['page_type', 'slug'])
            ->groupBy(fn ($p) => $p->page_type->value);

        $map = [
            'home' => $base . '/',
            'login' => route('login'),
            'preview' => route('site.preview', $site),
        ];

        // 每個模板取 sort_order 最小（第一筆）的 slug
        foreach (PageTemplate::cases() as $template) {
            if ($template === PageTemplate::Home) continue;
            $slug = $pages->get($template->value)?->first()?->slug ?? $template->value;
            $map[$template->value] = $base . '/' . $slug;
        }

        // POST 路由與對應頁面同 URL
        $map['contactSubmit'] = $map['contact'] ?? $base . '/contact';
        $map['progressAuth'] = $map['progress'] ?? $base . '/progress';

        return $map;
    }

    protected function navigationFor(Request $request, Site $site): array
    {
        $items = $site->navigationItems()
            ->with(['children.page', 'page'])
            ->whereNull('parent_id')
            ->where('is_visible', true)
            ->orderBy('position')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('position');

        return collect(['primary', 'secondary', 'footer'])
            ->mapWithKeys(fn (string $position) => [
                $position => $this->mapNavigationItems($request, $site, $items->get($position, collect())),
            ])
            ->all();
    }

    protected function mapNavigationItems(Request $request, Site $site, Collection $items): array
    {
        return $items->map(function ($item) use ($request, $site) {
            return [
                'label' => $item->label,
                'url' => $this->navigationUrl($request, $site, $item),
                'target' => $item->target,
                'children' => $this->mapNavigationItems($request, $site, $item->children),
            ];
        })->values()->all();
    }

    protected function navigationUrl(Request $request, Site $site, $item): string
    {
        if ($item->page) {
            $base = $this->isPreview($request) ? '/preview/' . $site->slug : '';
            if ($item->page->page_type === PageTemplate::Home) {
                return $base . '/';
            }
            return $base . '/' . $item->page->slug;
        }

        if ($this->isPreview($request) && str_starts_with((string) $item->url, '/')) {
            return rtrim(route('site.preview', $site), '/') . $item->url;
        }

        return $item->url ?: '#';
    }

    protected function canonicalUrl(Request $request, Site $site): string
    {
        if ($this->isPreview($request)) {
            $primaryDomain = $site->domains()->first()?->domain;
            if ($primaryDomain) {
                $path = str_replace('/preview/' . $site->slug, '', $request->getPathInfo());
                return $request->getScheme() . '://' . $primaryDomain . ($path ?: '/');
            }
        }
        return $request->url();
    }

    protected function metaDescription(?string $seoDescription, ?string $summary, ?string $content = null): string
    {
        if (filled($seoDescription)) return $seoDescription;
        if (filled($summary)) return Str::limit(strip_tags($summary), 160);
        if (filled($content)) return Str::limit(strip_tags($content), 160);
        return '';
    }

    protected function resolveSite(Request $request): Site
    {
        $site = $request->route('site') ?? $request->attributes->get('currentSite');

        if (is_string($site)) {
            $site = Site::where('slug', $site)->first();
        }

        abort_if(! $site instanceof Site, 404, 'Site not found for this domain.');
        abort_if(! $site->is_active && ! $this->isPreview($request), 404);

        return $site;
    }

    protected function isPreview(Request $request): bool
    {
        return $request->route()?->named('site.preview*') ?? false;
    }

    protected function findPageByTemplate(Site $site, PageTemplate $template): ?Page
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->where('page_type', $template->value)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->first();
    }

    protected function projectsQuery(Site $site)
    {
        return Project::query()->where('site_id', $site->id)->with('projectStatus');
    }

    protected function newsQuery(Site $site)
    {
        return NewsArticle::query()
            ->where('site_id', $site->id)
            ->where('is_published', true)
            ->with('newsCategory')
            ->orderByDesc('published_at');
    }

    protected function progressQuery(Site $site)
    {
        return ProgressUpdate::query()
            ->where('site_id', $site->id)
            ->where('is_published', true)
            ->orderByDesc('reported_at');
    }
}
