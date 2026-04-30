<?php

declare(strict_types=1);

namespace App\Services\Stubs;

use App\Services\Contracts\TtsContract;

final class StubTts implements TtsContract
{
    public function synthesize(string $text, string $voiceName = 'zh-TW-HsiaoChenNeural'): array
    {
        return [
            'audio_url' => 'https://placehold.co/audio.mp3',
            'remote_url' => 'https://placehold.co/audio.mp3',
            'duration_seconds' => mb_strlen($text) * 0.3,
        ];
    }
}
