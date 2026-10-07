<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ProductStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Importing = 'importing';
    case ImportFailed = 'import_failed';
    case ProductPendingReview = 'product_pending_review';
    case ProductApproved = 'product_approved';
    case ScriptGenerating = 'script_generating';
    case ScriptFailed = 'script_failed';
    case ScriptPendingReview = 'script_pending_review';
    case ScriptApproved = 'script_approved';
    case AssetsGenerating = 'assets_generating';
    case AssetsPartial = 'assets_partial';
    case AssetsPendingReview = 'assets_pending_review';
    case AssetsApproved = 'assets_approved';
    case Rendering = 'rendering';
    case RenderFailed = 'render_failed';
    case FinalPendingReview = 'final_pending_review';
    case ReadyToPublish = 'ready_to_publish';
    case Publishing = 'publishing';
    case PublishDraftFilled = 'publish_draft_filled';
    case PublishFailed = 'publish_failed';
    case Published = 'published';
    case Completed = 'completed';
    case NeedsManual = 'needs_manual';
    case Archived = 'archived';

    /**
     * 合法狀態轉移表（key 與 value 皆為 status value）
     */
    public const TRANSITIONS = [
        'draft' => ['importing', 'product_pending_review', 'archived'],
        'importing' => ['product_pending_review', 'import_failed', 'needs_manual', 'archived'],
        'import_failed' => ['importing', 'draft', 'archived'],
        'product_pending_review' => ['product_pending_review', 'product_approved', 'archived'],
        'product_approved' => ['script_generating', 'product_pending_review', 'archived'],
        'script_generating' => ['script_pending_review', 'script_failed', 'needs_manual', 'archived'],
        'script_failed' => ['script_generating', 'needs_manual', 'archived'],
        'script_pending_review' => ['script_pending_review', 'script_approved', 'script_generating', 'needs_manual', 'archived'],
        'script_approved' => ['assets_generating', 'assets_pending_review', 'script_pending_review', 'archived'],
        'assets_generating' => ['assets_pending_review', 'assets_partial', 'archived'],
        'assets_partial' => ['assets_generating', 'assets_pending_review', 'archived'],
        'assets_pending_review' => ['assets_approved', 'script_pending_review', 'archived'],
        'assets_approved' => ['rendering', 'assets_pending_review', 'archived'],
        'rendering' => ['final_pending_review', 'render_failed', 'archived'],
        'render_failed' => ['rendering', 'assets_pending_review', 'archived'],
        'final_pending_review' => ['ready_to_publish', 'script_pending_review', 'archived'],
        'ready_to_publish' => ['ready_to_publish', 'publishing', 'final_pending_review', 'archived'],
        'publishing' => ['publish_draft_filled', 'publish_failed', 'needs_manual', 'archived'],
        'publish_draft_filled' => ['published', 'ready_to_publish', 'archived'],
        'publish_failed' => ['publishing', 'ready_to_publish', 'archived'],
        'published' => ['completed', 'archived'],
        'completed' => ['archived'],
        'needs_manual' => ['product_pending_review', 'script_generating', 'script_pending_review', 'publishing', 'archived'],
        'archived' => [],
    ];

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Importing => '商品匯入中',
            self::ImportFailed => '商品匯入失敗',
            self::ProductPendingReview => '① 商品資料待確認',
            self::ProductApproved => '商品資料已確認',
            self::ScriptGenerating => '腳本生成中',
            self::ScriptFailed => '腳本生成失敗',
            self::ScriptPendingReview => '② 腳本待審核',
            self::ScriptApproved => '腳本已核准',
            self::AssetsGenerating => '素材處理中',
            self::AssetsPartial => '部分素材失敗',
            self::AssetsPendingReview => '③ 素材待審核',
            self::AssetsApproved => '素材已核准',
            self::Rendering => '影片渲染中',
            self::RenderFailed => '渲染失敗',
            self::FinalPendingReview => '④ 成品待審核',
            self::ReadyToPublish => '待上架',
            self::Publishing => '上架中（自動填表）',
            self::PublishDraftFilled => '草稿已填妥，待人工發布',
            self::PublishFailed => '上架失敗',
            self::Published => '已發布',
            self::Completed => '完成',
            self::NeedsManual => '需人工介入',
            self::Archived => '已封存',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Importing => 'info',
            self::ImportFailed => 'danger',
            self::ProductPendingReview => 'warning',
            self::ProductApproved => 'success',
            self::ScriptGenerating => 'info',
            self::ScriptFailed => 'danger',
            self::ScriptPendingReview => 'warning',
            self::ScriptApproved => 'success',
            self::AssetsGenerating => 'info',
            self::AssetsPartial => 'danger',
            self::AssetsPendingReview => 'warning',
            self::AssetsApproved => 'success',
            self::Rendering => 'info',
            self::RenderFailed => 'danger',
            self::FinalPendingReview => 'warning',
            self::ReadyToPublish => 'primary',
            self::Publishing => 'info',
            self::PublishDraftFilled => 'warning',
            self::PublishFailed => 'danger',
            self::Published => 'success',
            self::Completed => 'success',
            self::NeedsManual => 'danger',
            self::Archived => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::Importing => 'heroicon-o-arrow-down-tray',
            self::ImportFailed => 'heroicon-o-x-circle',
            self::ProductPendingReview => 'heroicon-o-clipboard-document-check',
            self::ProductApproved => 'heroicon-o-check',
            self::ScriptGenerating => 'heroicon-o-sparkles',
            self::ScriptFailed => 'heroicon-o-x-circle',
            self::ScriptPendingReview => 'heroicon-o-document-text',
            self::ScriptApproved => 'heroicon-o-check',
            self::AssetsGenerating => 'heroicon-o-cog-6-tooth',
            self::AssetsPartial => 'heroicon-o-exclamation-triangle',
            self::AssetsPendingReview => 'heroicon-o-photo',
            self::AssetsApproved => 'heroicon-o-check',
            self::Rendering => 'heroicon-o-film',
            self::RenderFailed => 'heroicon-o-x-circle',
            self::FinalPendingReview => 'heroicon-o-play-circle',
            self::ReadyToPublish => 'heroicon-o-paper-airplane',
            self::Publishing => 'heroicon-o-arrow-up-tray',
            self::PublishDraftFilled => 'heroicon-o-hand-raised',
            self::PublishFailed => 'heroicon-o-x-circle',
            self::Published => 'heroicon-o-check-badge',
            self::Completed => 'heroicon-o-trophy',
            self::NeedsManual => 'heroicon-o-user',
            self::Archived => 'heroicon-o-archive-box',
        };
    }

    /**
     * 是否為 4 個人工 checkpoint 之一
     */
    public function isCheckpoint(): bool
    {
        return in_array($this, [self::ProductPendingReview, self::ScriptPendingReview, self::AssetsPendingReview, self::FinalPendingReview], true);
    }

    /**
     * 是否為系統處理中（UI 應顯示進行中並禁止操作）
     */
    public function isProcessing(): bool
    {
        return in_array($this, [self::Importing, self::ScriptGenerating, self::AssetsGenerating, self::Rendering, self::Publishing], true);
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$this->value] ?? [], true);
    }
}
