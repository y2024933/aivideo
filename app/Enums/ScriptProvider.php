<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * LLM 寫稿供應商。
 *
 * 預設 Gemini：Google AI Studio 的免費 tier 不綁信用卡，本專案的量
 * （100–300 支／月，約每天 3–10 次呼叫）遠低於配額上限。
 * Claude 保留當品質備案 —— 同一份 prompt 跑出來的稿子較穩，但按次計費。
 */
enum ScriptProvider: string implements HasLabel
{
    case Gemini = 'gemini';
    case Claude = 'claude';
    case Stub = 'stub';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gemini => 'Gemini（免費額度）',
            self::Claude => 'Claude Opus 5（付費，品質較佳）',
            self::Stub => 'Stub（測試用）',
        };
    }
}
