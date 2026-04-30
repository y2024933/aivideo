<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\TtsContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class AzureTts implements TtsContract
{
    private string $key;
    private string $region;

    public function __construct()
    {
        $this->key = config('services.azure_tts.key')
            ?? throw new RuntimeException('AZURE_TTS_KEY is not configured');
        $this->region = config('services.azure_tts.region')
            ?? throw new RuntimeException('AZURE_TTS_REGION is not configured');
    }

    /** @inheritDoc */
    public function synthesize(string $text, string $voiceName = 'zh-TW-HsiaoChenNeural'): array
    {
        $processedText = $this->convertMandarinNumbers($text);
        $ssml = $this->buildSsml($processedText, $voiceName);

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->key,
            'X-Microsoft-OutputFormat' => 'audio-24khz-48kbitrate-mono-mp3',
        ])
            ->withBody($ssml, 'application/ssml+xml')
            ->timeout(30)
            ->post("https://{$this->region}.tts.speech.microsoft.com/cognitiveservices/v1");

        if (! $response->successful()) {
            Log::error('[AzureTts::synthesize] Azure TTS API 失敗', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("Azure TTS failed: HTTP {$response->status()}");
        }

        // 儲存 MP3 檔案（用 public disk，權限正確）
        $filename = Str::uuid()->toString() . '.mp3';
        $audioContent = $response->body();
        Storage::disk('public')->put("voiceovers/{$filename}", $audioContent);

        // 上傳到 S3（供 Remotion Lambda 存取）
        $remoteUrl = null;
        try {
            Storage::disk('s3')->put("voiceovers/{$filename}", $audioContent, 'public');
            $remoteUrl = Storage::disk('s3')->url("voiceovers/{$filename}");
        } catch (\Throwable $e) {
            Log::error('[AzureTts::synthesize] S3 上傳失敗，僅保留本地檔案', ['exception' => $e]);
        }

        // 用中文字數估算時長：每字約 0.35 秒
        $charCount = mb_strlen(preg_replace('/\s+/u', '', $processedText));
        $durationSeconds = round($charCount * 0.35, 1);

        $audioUrl = rtrim(config('app.url'), '/') . "/storage/voiceovers/{$filename}";

        return [
            'audio_url' => $audioUrl,
            'remote_url' => $remoteUrl,
            'duration_seconds' => $durationSeconds,
        ];
    }

    /** 數字轉中文（支援 0-9999） */
    public function convertMandarinNumbers(string $text): string
    {
        return preg_replace_callback('/(\d+)/', function (array $matches): string {
            return self::numberToChinese((int) $matches[1]);
        }, $text);
    }

    /** 單一數字轉中文 */
    private static function numberToChinese(int $num): string
    {
        $digits = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];

        if ($num < 0 || $num > 9999) {
            return (string) $num; // 超出範圍不轉換
        }

        if ($num <= 10) {
            return $num === 10 ? '十' : $digits[$num];
        }

        $result = '';

        // 千位
        if ($num >= 1000) {
            $result .= $digits[(int) ($num / 1000)] . '千';
            $num %= 1000;
            if ($num > 0 && $num < 100) {
                $result .= '零';
            }
        }

        // 百位
        if ($num >= 100) {
            $result .= $digits[(int) ($num / 100)] . '百';
            $num %= 100;
            if ($num > 0 && $num < 10) {
                $result .= '零';
            }
        }

        // 十位
        if ($num >= 10) {
            $tens = (int) ($num / 10);
            // 十幾不需要「一十」，直接「十」（僅用於整體數字就是十幾時）
            $result .= ($tens === 1 && $result === '' ? '' : $digits[$tens]) . '十';
            $num %= 10;
        }

        // 個位
        if ($num > 0) {
            $result .= $digits[$num];
        }

        return $result;
    }

    /** 組合 SSML XML */
    private function buildSsml(string $text, string $voiceName): string
    {
        $escapedText = htmlspecialchars($text, ENT_XML1, 'UTF-8');

        return "<speak version='1.0' xml:lang='zh-TW'><voice xml:lang='zh-TW' xml:gender='Female' name='{$voiceName}'>{$escapedText}</voice></speak>";
    }
}
