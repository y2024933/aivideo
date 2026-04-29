<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface TtsContract
{
    /**
     * 文字轉語音
     * @return array{audio_url: string, duration_seconds: float}
     */
    public function synthesize(string $text, string $voiceName = 'zh-TW-HsiaoChenNeural'): array;
}
