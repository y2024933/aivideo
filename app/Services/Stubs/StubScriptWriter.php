<?php

declare(strict_types=1);

namespace App\Services\Stubs;

use App\Data\Llm\ScriptOutput;
use App\Data\Llm\ScriptShot;
use App\Models\Product;
use App\Services\Contracts\ScriptWriterContract;
use Throwable;

/**
 * 寫稿 stub：不連外、不花錢，回一份乾淨的 5 鏡假稿。
 *
 * 測試要驗「違規 → 重試 → NeedsManual」這條路徑，所以刻意把輸出做成可注入：
 *   $writer = new StubScriptWriter(StubScriptWriter::simplifiedOutput());
 *   $writer->queue = [$bad, $bad];         // 依序回傳，模擬重試後仍違規
 *   $writer->throws = new RuntimeException(...);  // 模擬 API 掛掉
 *   $writer->calls                          // 每次 write() 收到的 $options，驗 retry_feedback
 *
 * 刻意用實例屬性而不是 static：static 會跨測試殘留，忘記 reset 就會出現
 * 「單獨跑綠、整包跑紅」的幽靈失敗。測試用 app()->instance() 綁同一個實例即可。
 */
final class StubScriptWriter implements ScriptWriterContract
{
    /** @var list<array<string, mixed>> 每次 write() 收到的 */
    public array $calls = [];

    /** @var list<ScriptOutput> 依序取用；用完退回內建乾淨稿 */
    public array $queue = [];

    public ?Throwable $throws = null;

    public function __construct(?ScriptOutput $nextOutput = null)
    {
        if ($nextOutput !== null) {
            $this->queue[] = $nextOutput;
        }
    }

    public function write(Product $product, array $options = []): ScriptOutput
    {
        $this->calls[] = $options;

        if ($this->throws !== null) {
            throw $this->throws;
        }

        return array_shift($this->queue) ?? self::cleanOutput((string) $product->audio_mode === 'tts');
    }

    public function lastUsage(): array
    {
        // cost 刻意非零，記帳測試才驗得出 addLlmCost() 有被呼叫
        return ['input' => 4200, 'output' => 850, 'cache_read' => 3800, 'cache_write' => 0, 'cost_usd' => 0.0253, 'model' => 'stub-script-writer'];
    }

    /**
     * 乾淨的 5 鏡假稿：純繁體、不含任何 blocking／warning 命中詞、caption 不含揭露前綴。
     *
     * ⚠️ 不要在這裡用「降噪」「續航」「防水」—— 它們是 spec_overclaim 的 warning 詞，
     * 會讓所有「正常流程」的測試變成 compliance_passed = false。
     */
    public static function cleanOutput(bool $withVoiceover = false): ScriptOutput
    {
        $rows = [
            ['hook', 0, 'zoomIn', 2.0, '出門才發現耳機又沒電', 'cut'],
            ['pain', 1, 'panRight', 3.0, '通勤整路只剩引擎聲', 'crossfade'],
            ['feature', 2, 'zoomOut', 3.5, '戴上就安靜，像關掉外面的世界', 'crossfade'],
            ['feature', 3, 'panUp', 3.0, '一次充飽，聽到晚上回家', 'cut'],
            ['cta', 0, 'zoomInPanUp', 2.5, '想入手的看左下連結', 'crossfade'],
        ];

        return new ScriptOutput(
            shots: array_map(fn (array $row) => new ScriptShot(
                role: $row[0],
                imageRef: $row[1],
                kenBurns: $row[2],
                durationSeconds: $row[3],
                subtitle: $row[4],
                voiceoverText: $withVoiceover ? $row[4].'，這點我真的很有感。' : '',
                transition: $row[5],
            ), $rows),
            caption: '通勤路上想要一點安靜，這款耳機我每天都在用，分享給同樣怕吵的你。',
            hashtags: ['藍牙耳機', '通勤好物', '耳機推薦'],
        );
    }

    /** 字幕含簡體字（「这」「关」「钟」）的假稿，驗重試與 NeedsManual 路徑 */
    public static function simplifiedOutput(): ScriptOutput
    {
        $output = self::cleanOutput();
        $output->shots[2]->subtitle = '这款戴上就安静，像关掉世界';

        return $output;
    }

    /** 含 blocking 違規（guarantee）的假稿 */
    public static function blockingOutput(): ScriptOutput
    {
        $output = self::cleanOutput();
        $output->shots[3]->subtitle = '保證最有效，三天見效';

        return $output;
    }
}
