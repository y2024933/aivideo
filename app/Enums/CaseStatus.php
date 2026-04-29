<?php

declare(strict_types=1);

namespace App\Enums;

enum CaseStatus: string
{
    case Draft = 'draft';
    case CharacterGenerating = 'character_generating';
    case CharacterPendingReview = 'character_pending_review';
    case CharacterFailed = 'character_failed';
    case CharacterApproved = 'character_approved';
    case ScriptPendingReview = 'script_pending_review';
    case ImagesGenerating = 'images_generating';
    case ImagesPartial = 'images_partial';
    case ImagesPendingReview = 'images_pending_review';
    case ImagesApproved = 'images_approved';
    case ProducingFinal = 'producing_final';
    case FinalPendingReview = 'final_pending_review';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::CharacterGenerating => '角色生成中',
            self::CharacterPendingReview => '角色待審核',
            self::CharacterFailed => '角色生成失敗',
            self::CharacterApproved => '角色已核准',
            self::ScriptPendingReview => '腳本待審核',
            self::ImagesGenerating => '場景圖生成中',
            self::ImagesPartial => '部分場景圖失敗',
            self::ImagesPendingReview => '場景圖待審核',
            self::ImagesApproved => '場景圖已核准',
            self::ProducingFinal => '產出最終素材中',
            self::FinalPendingReview => '成品待審核',
            self::Completed => '完成',
        };
    }
}
