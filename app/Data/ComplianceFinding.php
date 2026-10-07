<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * 單一違規命中點。
 *
 * offset / length 一律是「mb 字元位置」而非 byte offset —— 前端高亮直接拿去
 * slice 字串，byte offset 會把中文切半。
 */
final class ComplianceFinding extends Data
{
    /**
     * @param  string  $field  欄位路徑，例：'S02.subtitle' / 'caption' / 'hashtags' / 'title'
     * @param  int  $offset  mb 字元起始位置（0-based）
     * @param  int  $length  mb 字元長度
     * @param  string  $matched  實際命中的字串
     * @param  string  $severity  'blocking' | 'warning'
     * @param  list<string>  $suggestions  一簡對多繁時放全部候選（發/髮）
     */
    public function __construct(
        public string $field,
        public int $offset,
        public int $length,
        public string $matched,
        public string $ruleId,
        public string $severity,
        public string $category,
        public string $law,
        public string $message,
        public ?string $suggestion = null,
        public array $suggestions = [],
        public string $confidence = 'high',
        public bool $acknowledged = false,
    ) {}

    public function isBlocking(): bool
    {
        return $this->severity === 'blocking';
    }
}
