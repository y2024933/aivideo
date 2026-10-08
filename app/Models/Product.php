<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AudioMode;
use App\Enums\BrowserTaskStatus;
use App\Enums\BrowserTaskType;
use App\Enums\ProductStatus;
use App\Exceptions\IllegalStatusTransition;
use App\Services\Compliance\AdComplianceChecker;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class Product extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'category_path' => 'array',
        'variations' => 'array',
        'raw_payload' => 'array',
        'subtitle_settings' => 'array',
        'script' => 'array',
        'hashtags' => 'array',
        'compliance_report' => 'array',
        'title_has_simplified' => 'boolean',
        'compliance_passed' => 'boolean',
        'ai_labeled' => 'boolean',
        'price' => 'decimal:2',
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
        'price_before_discount' => 'decimal:2',
        'rating_star' => 'decimal:2',
        'bgm_volume' => 'decimal:2',
        'final_video_duration_sec' => 'decimal:1',
        'cost_usd' => 'decimal:4',
        'llm_cost_usd' => 'decimal:4',
        'scraped_at' => 'datetime',
        'script_generated_at' => 'datetime',
        'compliance_checked_at' => 'datetime',
        'published_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function selectedImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->where('is_selected', true)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class)->orderBy('shot_order');
    }

    public function voiceovers(): HasMany
    {
        return $this->hasMany(Voiceover::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProductStatusHistory::class)->orderByDesc('created_at');
    }

    public function browserTasks(): MorphMany
    {
        return $this->morphMany(BrowserTask::class, 'subject');
    }

    /**
     * 這個商品的資料是不是「降級抓取」來的（XHR 沒攔到，從 DOM 撈的）。
     *
     * ⚠️ 這是 checkpoint ① 自動放行的否決條件。DOM 解析的價格與規格可能缺漏或錯位，
     * 而 ① 之後就開始花錢，資料來源不可靠時一定要人看。
     */
    public function hasDegradedScrape(): bool
    {
        return $this->browserTasks()
            ->where('type', BrowserTaskType::ShopeeScrapeProduct)
            ->where('status', BrowserTaskStatus::Degraded)
            ->exists();
    }

    /**
     * 依 TRANSITIONS 驗證後轉移狀態，不合法則丟 IllegalStatusTransition
     */
    public function transitionTo(ProductStatus $to, string $by = 'system', ?string $note = null): void
    {
        $from = $this->status;

        if (! in_array($to->value, ProductStatus::TRANSITIONS[$from->value] ?? [], true)) {
            throw new IllegalStatusTransition("不允許的狀態轉移：{$from->value} → {$to->value}");
        }

        $this->writeStatus($from, $to, $by, $note);
    }

    /**
     * 繞過驗證強制設定狀態（僅限解除封存等管理操作）
     */
    public function forceStatus(ProductStatus $to, string $by, ?string $note = null): void
    {
        $this->writeStatus($this->status, $to, $by, $note);
    }

    private function writeStatus(?ProductStatus $from, ProductStatus $to, string $by, ?string $note): void
    {
        $this->update(['status' => $to]);

        $this->statusHistory()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'triggered_by' => $by,
            'note' => $note,
        ]);
    }

    public function addCost(float $amount): void
    {
        $this->increment('cost_usd', $amount);
    }

    /**
     * LLM 成本單獨記帳，不累加進 cost_usd。
     * 總成本 = cost_usd（圖片/動畫/配音/剪接）+ llm_cost_usd（寫稿），
     * 兩欄互斥以免重複計算。
     */
    public function addLlmCost(float $amount): void
    {
        $this->increment('llm_cost_usd', $amount);
    }

    /** 總成本（含 LLM） */
    public function totalCostUsd(): float
    {
        return (float) $this->cost_usd + (float) $this->llm_cost_usd;
    }

    public function totalDurationSeconds(): float
    {
        return (float) $this->shots->sum(fn (Shot $shot) => (float) $shot->duration_seconds);
    }

    public function hasSimplifiedChinese(): bool
    {
        return $this->title_has_simplified
            || $this->shots->contains(fn (Shot $shot) => $shot->subtitle_has_simplified || $shot->voiceover_has_simplified);
    }

    public function complianceBlockingCount(): int
    {
        // compliance_report 存的是 ComplianceReport::toArray()（findings 在子層）；
        // 舊資料可能只存裸的 findings 陣列，兩種形狀都要算得出來。
        $findings = $this->compliance_report['findings'] ?? $this->compliance_report ?? [];

        return count(array_filter((array) $findings, fn ($item) => is_array($item) && ($item['severity'] ?? null) === 'blocking'));
    }

    /**
     * 阻止渲染的原因清單。空陣列才可以送 Remotion Lambda。
     *
     * 這裡擋的都是「渲染會成功但成品不能用」的情況 —— 渲染一支廢片要花錢也要花時間，
     * 寧可在送出前擋下來。
     *
     * @return array<int, string>
     */
    public function renderBlockers(): array
    {
        $blockers = [];

        if ($this->shots->filter(fn (Shot $shot) => $shot->renderableUrl() !== null)->count() < 2) {
            $blockers[] = '可渲染鏡頭不足 2 個（需有 image_remote_url 或 video_remote_url）';
        }

        if ($this->shots->contains(fn (Shot $shot) => $shot->subtitle_has_simplified || $shot->voiceover_has_simplified)) {
            $blockers[] = '有鏡頭字幕含簡體字';
        }

        if ($this->shots->contains(fn (Shot $shot) => collect($shot->compliance_flags ?? [])
            ->contains(fn ($flag) => ($flag['category'] ?? null) === 'glyph_variant'))) {
            $blockers[] = '有鏡頭字幕含大陸字形（Noto TC 字型可能缺字，會渲染成方塊 □）';
        }

        if (! $this->compliance_passed) {
            $blockers[] = '合規檢查未通過';
        }

        // 規則檔改過但這個商品還是用舊規則掃的 → 必須重掃，否則等於沒檢查
        if ($this->compliance_rules_fingerprint !== app(AdComplianceChecker::class)->rulesFingerprint()) {
            $blockers[] = '合規規則已更新，請重新檢查';
        }

        if (blank($this->disclosure_prefix)) {
            $blockers[] = '未設定聯盟行銷揭露前綴';
        }

        if ($this->audio_mode === AudioMode::Tts->value
            && $this->shots->contains(fn (Shot $shot) => filled($shot->voiceover_text) && $shot->voiceover_status !== 'done')) {
            $blockers[] = '配音模式但有鏡頭配音未完成';
        }

        if (filled($this->render_id)) {
            $blockers[] = '已有渲染任務進行中';
        }

        return $blockers;
    }
}
