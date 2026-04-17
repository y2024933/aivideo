<?php

namespace App\Http\Controllers;

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
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function home(Request $request): Response
    {
        $site = $this->resolveSite($request);

        return Inertia::render('Site/Home', [
            ...$this->sharedProps($request, $site),
            'homePage' => $this->findPage($site, 'home'),
            'aboutPage' => $this->findPage($site, 'about'),
            'projectsPage' => $this->findPage($site, 'projects'),
            'newsPage' => $this->findPage($site, 'news'),
            'servicesPage' => $this->findPage($site, 'services'),
            'progressPage' => $this->findPage($site, 'progress'),
            'contactPage' => $this->findPage($site, 'contact'),
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
                ->where('status', 'completed')
                ->orderByDesc('launch_year')
                ->get(),
            'homepageSections' => $site->setting?->homepage_sections ?? [],
        ]);
    }

    public function about(Request $request): Response
    {
        $site = $this->resolveSite($request);

        return Inertia::render('Site/About', [
            ...$this->sharedProps($request, $site),
            'page' => $this->findPage($site, 'about'),
        ]);
    }

    public function projects(Request $request): Response
    {
        $site = $this->resolveSite($request);
        $activeStatus = $request->string('status')->toString();
        $query = $this->projectsQuery($site);

        if (filled($activeStatus)) {
            $query->where('status', $activeStatus);
        }

        return Inertia::render('Site/Projects/Index', [
            ...$this->sharedProps($request, $site),
            'page' => $this->findPage($site, 'projects'),
            'projects' => $query
                ->orderByDesc('is_featured')
                ->orderByDesc('launch_year')
                ->orderBy('sort_order')
                ->get(),
            'activeStatus' => $activeStatus,
        ]);
    }

    public function projectShow(Request $request, string $slug): Response
    {
        $site = $this->resolveSite($request);
        $project = Project::query()
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Site/Projects/Show', [
            ...$this->sharedProps($request, $site),
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

    public function news(Request $request): Response
    {
        $site = $this->resolveSite($request);
        $activeCategory = $request->string('category')->toString();
        $query = $this->newsQuery($site);

        $categories = NewsArticle::query()
            ->where('site_id', $site->id)
            ->where('is_published', true)
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->all();

        if (filled($activeCategory)) {
            $query->where('category', $activeCategory);
        }

        return Inertia::render('Site/News/Index', [
            ...$this->sharedProps($request, $site),
            'page' => $this->findPage($site, 'news'),
            'articles' => $query->get(),
            'categories' => $categories,
            'activeCategory' => $activeCategory,
        ]);
    }

    public function newsShow(Request $request, string $slug): Response
    {
        $site = $this->resolveSite($request);
        $article = NewsArticle::query()
            ->where('site_id', $site->id)
            ->where('is_published', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Site/News/Show', [
            ...$this->sharedProps($request, $site),
            'article' => $article,
            'relatedArticles' => $this->newsQuery($site)
                ->where('id', '!=', $article->id)
                ->limit(3)
                ->get(),
        ]);
    }

    public function services(Request $request): Response
    {
        $site = $this->resolveSite($request);
        $serviceCards = $site->setting?->service_content['cards'] ?? [];

        return Inertia::render('Site/Services', [
            ...$this->sharedProps($request, $site),
            'page' => $this->findPage($site, 'services'),
            'serviceHighlights' => filled($serviceCards) ? $serviceCards : [
                [
                    'title' => '代租代管',
                    'description' => '從招租、簽約到入住後的維護與回報，建立一套穩定的管理流程。',
                ],
                [
                    'title' => '售後維護',
                    'description' => '延續建築品質的維護節奏，將售後服務納入品牌體驗的一部分。',
                ],
                [
                    'title' => '不動產顧問',
                    'description' => '整合建案、屋主與資產配置需求，提供更完整的長期規劃建議。',
                ],
            ],
            'latestNews' => $this->newsQuery($site)->limit(3)->get(),
        ]);
    }

    public function progress(Request $request): Response
    {
        $site = $this->resolveSite($request);
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

        return Inertia::render('Site/Progress', [
            ...$this->sharedProps($request, $site),
            'page' => $this->findPage($site, 'progress'),
            'projects' => $this->projectsQuery($site)->get(['id', 'name', 'slug']),
            'isAuthenticated' => (bool) $project,
            'selectedProject' => $project,
            'updates' => $updates,
            'albums' => $albums,
        ]);
    }

    public function progressAuth(Request $request)
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

    public function progressAlbum(Request $request, ProgressAlbum $album): Response
    {
        $site = $this->resolveSite($request);

        // 驗證相簿屬於該站台且已發布
        abort_unless($album->site_id === $site->id && $album->is_published, 404);

        // 驗證已登入工程進度
        $authenticatedProjectId = session('progress_project_id');
        abort_unless($authenticatedProjectId && $authenticatedProjectId == $album->project_id, 403);

        $album->load('project');

        return Inertia::render('Site/ProgressAlbum', [
            ...$this->sharedProps($request, $site),
            'album' => $album,
        ]);
    }

    public function contact(Request $request): Response
    {
        $site = $this->resolveSite($request);
        $inquiryTypes = $site->setting?->contact_content['inquiry_types'] ?? [];

        return Inertia::render('Site/Contact', [
            ...$this->sharedProps($request, $site),
            'page' => $this->findPage($site, 'contact'),
            'projects' => $this->projectsQuery($site)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug']),
            'inquiryTypes' => filled($inquiryTypes) ? $inquiryTypes : [
                '預約看屋',
                '線上報修',
                '包租代管',
                '合作提案',
                '建議事項',
                '其他',
            ],
        ]);
    }

    public function submitContact(Request $request): RedirectResponse
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

        ContactMessage::create([
            ...$validated,
            'site_id' => $site->id,
            'status' => 'new',
            'source_page' => $request->path(),
        ]);

        return back()->with('success', '已收到您的訊息，我們會盡快與您聯繫。');
    }

    protected function sharedProps(Request $request, Site $site): array
    {
        $site->loadMissing('setting');

        return [
            'site' => $site,
            'navigation' => $this->navigationFor($request, $site),
            'routeMap' => [
                'home' => $this->routeFor($request, $site, 'home'),
                'about' => $this->routeFor($request, $site, 'about'),
                'projects' => $this->routeFor($request, $site, 'projects'),
                'news' => $this->routeFor($request, $site, 'news'),
                'services' => $this->routeFor($request, $site, 'services'),
                'progress' => $this->routeFor($request, $site, 'progress'),
                'contact' => $this->routeFor($request, $site, 'contact'),
                'contactSubmit' => $this->routeFor($request, $site, 'contact.submit'),
                'progressAuth' => $this->routeFor($request, $site, 'progress.auth'),
                'login' => route('login'),
                'preview' => route('site.preview', $site),
            ],
            'isPreview' => $this->isPreview($request),
        ];
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
            return match ($item->page->slug) {
                'home' => $this->routeFor($request, $site, 'home'),
                'about' => $this->routeFor($request, $site, 'about'),
                'projects' => $this->routeFor($request, $site, 'projects'),
                'news' => $this->routeFor($request, $site, 'news'),
                'services' => $this->routeFor($request, $site, 'services'),
                'progress' => $this->routeFor($request, $site, 'progress'),
                'contact' => $this->routeFor($request, $site, 'contact'),
                default => $item->url ?: '#',
            };
        }

        if ($this->isPreview($request) && str_starts_with((string) $item->url, '/')) {
            return rtrim(route('site.preview', $site), '/').$item->url;
        }

        return $item->url ?: '#';
    }

    protected function routeFor(Request $request, Site $site, string $page, array $parameters = []): string
    {
        $name = match (true) {
            $page === 'home' && $this->isPreview($request) => 'site.preview',
            $page === 'home' => 'site.home',
            $this->isPreview($request) => "site.preview.{$page}",
            default => "site.{$page}",
        };
        $base = $this->isPreview($request) ? ['site' => $site] : [];

        return route($name, [...$base, ...$parameters]);
    }

    protected function resolveSite(Request $request): Site
    {
        $site = $request->route('site') ?? $request->attributes->get('currentSite');

        abort_if(! $site instanceof Site, 404, 'Site not found for this domain.');
        abort_if(! $site->is_active, 404);

        return $site;
    }

    protected function isPreview(Request $request): bool
    {
        return $request->route()?->named('site.preview*') ?? false;
    }

    protected function findPage(Site $site, string $slug): ?Page
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }

    protected function projectsQuery(Site $site)
    {
        return Project::query()->where('site_id', $site->id);
    }

    protected function newsQuery(Site $site)
    {
        return NewsArticle::query()
            ->where('site_id', $site->id)
            ->where('is_published', true)
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
