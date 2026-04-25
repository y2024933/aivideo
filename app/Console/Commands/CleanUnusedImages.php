<?php

namespace App\Console\Commands;

use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\ProgressAlbum;
use App\Models\ProgressUpdate;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanUnusedImages extends Command
{
    protected $signature = 'app:clean-images {--dry-run : 只列出不刪除} {--force : 跳過確認直接刪除}';

    protected $description = '清理未被資料庫引用的孤兒圖片';

    // 所有圖片目錄
    private const DIRECTORIES = [
        'site-logos', 'site-favicons', 'site-hero-images',
        'project-images', 'page-gallery', 'page-images', 'news-images',
        'progress-albums', 'editor-attachments', 'images',
    ];

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $referenced = $this->collectReferencedPaths();

        // 掃描所有圖片目錄中的檔案
        $allFiles = collect();
        foreach (self::DIRECTORIES as $dir) {
            if ($disk->exists($dir)) {
                $allFiles = $allFiles->merge($disk->allFiles($dir));
            }
        }

        $orphans = $allFiles->reject(fn ($file) => $referenced->contains($file));

        if ($orphans->isEmpty()) {
            $this->info('沒有找到孤兒圖片，一切乾淨！');
            return self::SUCCESS;
        }

        $totalSize = $orphans->sum(fn ($file) => $disk->size($file));
        $this->warn("找到 {$orphans->count()} 個孤兒檔案，共 " . $this->formatSize($totalSize));

        if ($this->option('dry-run')) {
            $orphans->each(fn ($file) => $this->line("  {$file} (" . $this->formatSize($disk->size($file)) . ')'));
            $this->info('以上為預覽，加 --force 或不加 --dry-run 來實際刪除。');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('確定要刪除這些檔案？')) {
            $this->info('已取消。');
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($orphans as $file) {
            $disk->delete($file);
            $deleted++;
        }

        $this->info("已刪除 {$deleted} 個孤兒檔案，釋放 " . $this->formatSize($totalSize));

        return self::SUCCESS;
    }

    private function collectReferencedPaths(): \Illuminate\Support\Collection
    {
        $paths = collect();

        // Site: logo, favicon, hero background
        Site::select('logo_path', 'favicon_path', 'hero_content')->get()->each(function ($site) use (&$paths) {
            $paths->push($site->logo_path, $site->favicon_path);
            $paths->push($site->hero_content['background_image'] ?? null);
        });

        // Page: cover image + gallery + editor content 中的圖片
        Page::select('cover_image_path', 'gallery', 'content')->get()->each(function ($page) use (&$paths) {
            $paths->push($page->cover_image_path);
            foreach ($page->gallery ?? [] as $img) {
                $paths->push($img);
            }
            $this->extractContentImages($page->content, $paths);
        });

        // Project: featured image
        Project::select('featured_image_path')->get()->each(fn ($p) => $paths->push($p->featured_image_path));

        // NewsArticle: featured image + editor content
        NewsArticle::select('featured_image_path', 'content')->get()->each(function ($article) use (&$paths) {
            $paths->push($article->featured_image_path);
            $this->extractContentImages($article->content, $paths);
        });

        // ProgressAlbum: gallery
        ProgressAlbum::select('gallery')->get()->each(function ($album) use (&$paths) {
            foreach ($album->gallery ?? [] as $img) {
                $paths->push($img);
            }
        });

        // ProgressUpdate: gallery + editor content
        ProgressUpdate::select('gallery', 'content')->get()->each(function ($update) use (&$paths) {
            foreach ($update->gallery ?? [] as $img) {
                $paths->push($img);
            }
            $this->extractContentImages($update->content, $paths);
        });

        return $paths->filter()->unique();
    }

    /** 從 TipTap HTML content 中抽取圖片路徑 */
    private function extractContentImages(?string $content, \Illuminate\Support\Collection &$paths): void
    {
        if (! $content) return;

        // 匹配 src="..." 中的圖片路徑（含絕對與相對 URL）
        $appUrl = rtrim(config('app.url'), '/');
        preg_match_all('/src=["\']([^"\']+)["\']/', $content, $matches);
        foreach ($matches[1] ?? [] as $raw) {
            // 同網域絕對 URL 轉相對路徑
            $path = str_starts_with($raw, $appUrl) ? substr($raw, strlen($appUrl)) : $raw;
            // 排除外部 URL
            if (str_starts_with($path, 'http')) continue;
            // 移除 /storage/ 前綴，取得 disk 相對路徑
            $paths->push(preg_replace('#^/storage/#', '', $path));
        }
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
