<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\ComplianceReport;
use App\Data\Llm\ScriptOutput;
use App\Enums\AudioMode;
use App\Enums\ProductStatus;
use App\Enums\ScriptProvider;
use App\Enums\ShotRole;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Compliance\AdComplianceChecker;
use App\Services\Compliance\TraditionalChineseValidator;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\Llm\ScriptDurationPlanner;
use App\Services\Llm\ScriptFields;
use App\Services\Llm\ScriptWriterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P3：LLM 自動寫稿 + 合規自動重試。
 *
 * 違規時最多重寫一次，上限刻意寫死在程式裡而不是 config —— 這是成本與無限迴圈的
 * 最後一道防線，不該有人能在 .env 裡把它調成 10。
 *
 * 重試後仍違規時「照樣把 shots 寫進 DB」再轉 NeedsManual：operator 需要看得到實際
 * 寫出來的字才改得動，只給一句「違規」等於要他重跑一次（又一次付費呼叫）。
 */
final class GenerateScriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 合規重試上限。⚠️ 刻意寫死，不要改成讀 config。 */
    private const MAX_RETRIES = 1;

    /**
     * 字幕字數上限。
     *
     * ⚠️ 這個限制在 ScriptShot 上有宣告 #[Constrained(maxLength: 22)]，但 SDK 的驗證是
     * StructuredOutput.php:259 的 strlen()（位元組）而非 mb_strlen —— 中文一字 3 bytes，
     * 22 字 = 66 bytes 必定「違規」，而違規只寫進 validation_warnings 不丟例外。
     * 等於 SDK 層對 CJK 完全沒有把關，必須在這裡自己檢查。
     *
     * 超長的後果：Subtitle.jsx 的 maxWidth 85% 會把字幕折成 2–3 行蓋住畫面。
     */
    private const MAX_SUBTITLE_CHARS = 22;

    public int $tries = 1;

    public int $timeout = 180;

    /** 可以進寫稿的狀態（NeedsManual 是人工修完商品資料後重跑） */
    private const ENTRY_STATUSES = [ProductStatus::ProductApproved, ProductStatus::ScriptFailed, ProductStatus::NeedsManual];

    public function __construct(public readonly string $productId)
    {
        // $queue 不能用屬性宣告覆寫：Illuminate\Bus\Queueable 已宣告同名屬性且預設值為 null，
        // 重新宣告（即使同型別）會觸發 trait 組合衝突的 FatalError。
        $this->onQueue('default');
    }

    public function handle(AdComplianceChecker $checker, TraditionalChineseValidator $zhTw): void
    {
        $product = Product::find($this->productId);

        if (! $product || ! in_array($product->status, self::ENTRY_STATUSES, true)) {
            return;
        }

        $product->transitionTo(ProductStatus::ScriptGenerating, 'llm');
        $options = [];
        $writer = null;

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                // writer 在這裡解析而不是用方法注入：缺 API key 時建構子會丟例外，
                // 方法注入的話例外會發生在 handle() 之外，商品就會卡在 script_generating
                // 而 operator 看不到任何原因。
                $writer ??= $this->resolveWriter($product);
                $output = $writer->write($product, $options);
            } catch (Throwable $e) {
                Log::error('[GenerateScriptJob] 寫稿失敗', ['product_id' => $product->id, 'exception' => $e]);
                $product->update(['status_message' => mb_substr($e->getMessage(), 0, 2000)]);
                $product->transitionTo(ProductStatus::ScriptFailed, 'llm');

                return;
            }

            $usage = $writer->lastUsage();
            // 失敗的那次也花了錢，每次呼叫後就記帳，不要等成功才記
            $product->addLlmCost((float) ($usage['cost_usd'] ?? 0));

            $fields = ScriptFields::fromOutput($product, $output);
            $report = $checker->check($fields, (string) ($product->compliance_profile ?: 'general'));
            $simplified = array_keys(array_filter($fields, fn (string $text) => $zhTw->findSimplifiedChars($text) !== []));
            $overlong = $this->overlongSubtitles($output);

            if ($report->blocking() === [] && $simplified === [] && $overlong === [] && ! $report->profileBlocked) {
                $this->persist($product, $output, $report, $zhTw, (string) ($usage['model'] ?? ''));
                $product->transitionTo(ProductStatus::ScriptPendingReview, 'llm');

                return;
            }

            // profileBlocked 是分類本身不給做，重寫幾次都一樣，直接送人工
            if ($attempt === self::MAX_RETRIES || $report->profileBlocked) {
                break;
            }

            $options = [
                'retry_report' => $report,
                'retry_feedback' => trim(implode("\n", array_filter([
                    $zhTw->buildRetryFeedback(array_intersect_key($fields, array_flip($simplified))),
                    $this->overlongFeedback($overlong),
                ]))),
            ];
        }

        $this->persist($product, $output, $report, $zhTw, (string) ($writer->lastUsage()['model'] ?? ''));
        $product->update(['needs_manual_reason' => $this->manualReason($report, $overlong ?? [])]);
        $product->transitionTo(ProductStatus::NeedsManual, 'llm');
    }

    /**
     * products.script_provider 有值就覆寫全域預設，否則走容器綁定。
     *
     * ⚠️ 沒指定時刻意解析 ScriptWriterContract 而不是直接呼叫 factory：容器綁定是測試
     * 用 app()->instance() 塞假 writer 的唯一入口，繞過去就等於拔掉測試的接縫。
     */
    private function resolveWriter(Product $product): ScriptWriterContract
    {
        $provider = ScriptProvider::tryFrom((string) $product->script_provider);

        return $provider !== null
            ? app(ScriptWriterFactory::class)->make($provider)
            : app(ScriptWriterContract::class);
    }

    /** 寫入腳本、文案與合規報告。成功與失敗路徑共用，差別只在之後轉哪個狀態。 */
    private function persist(Product $product, ScriptOutput $output, ComplianceReport $report, TraditionalChineseValidator $zhTw, string $model): void
    {
        $product->update([
            'script' => $output->jsonSerialize(),
            'script_model' => $model !== '' ? mb_substr($model, 0, 64) : null,
            'script_generated_at' => now(),
            'caption' => $output->caption,
            'hashtags' => array_values(array_map(fn ($tag) => ltrim((string) $tag, '#'), $output->hashtags)),
            'compliance_report' => $report->toArray(),
            'compliance_passed' => $report->passed(),
            'compliance_checked_at' => $report->checkedAt,
            'compliance_rules_version' => $report->rulesVersion,
            'compliance_rules_fingerprint' => $report->rulesFingerprint,
            'status_message' => null,
            'needs_manual_reason' => null,
        ]);

        $this->writeShots($product, $output, $report, $zhTw);
    }

    private function writeShots(Product $product, ScriptOutput $output, ComplianceReport $report, TraditionalChineseValidator $zhTw): void
    {
        $product->shots()->delete();   // 重跑時先清掉舊鏡頭，shot_id 有 unique 約束

        $images = $product->selectedImages()->get();
        $byShot = $report->byShot();
        $withVoiceover = (string) $product->audio_mode !== AudioMode::None->value;
        $durations = ScriptDurationPlanner::plan($output, (int) ($product->video_length_seconds ?: 30));

        foreach ($output->shots as $index => $shot) {
            $shotId = sprintf('S%02d', $index + 1);
            $image = $this->imageFor($images, $shot->imageRef);
            $voiceover = $withVoiceover ? trim($shot->voiceoverText) : '';

            $product->shots()->create([
                'shot_id' => $shotId,
                'shot_order' => $index + 1,
                'role' => (ShotRole::tryFrom($shot->role) ?? ShotRole::Other)->value,
                'duration_seconds' => $durations[$index],
                'subtitle' => $shot->subtitle,
                'subtitle_has_simplified' => $zhTw->findSimplifiedChars($shot->subtitle) !== [],
                'voiceover_text' => $voiceover !== '' ? $voiceover : null,
                'voiceover_has_simplified' => $zhTw->findSimplifiedChars($voiceover) !== [],
                'voiceover_status' => $voiceover !== '' ? 'pending' : 'skipped',
                'compliance_flags' => array_map(fn ($finding) => $finding->toArray(), $byShot[$shotId] ?? []),
                'transition' => $shot->transition,
                'ken_burns' => $shot->kenBurns,
                'product_image_id' => $image?->id,
                'image_url' => $image?->local_path,
                'image_remote_url' => $image?->remote_url,
                'fit' => $image?->suggestedFit() ?? 'contain',
            ]);
        }
    }

    /**
     * imageRef 超出範圍就取模。
     * LLM 偶爾會回 7 而我們只有 4 張圖，為此整份稿退回重寫不划算。
     *
     * @param  Collection<int, ProductImage>  $images
     */
    private function imageFor(Collection $images, int $imageRef): ?ProductImage
    {
        return $images->isEmpty() ? null : $images->values()->get(max($imageRef, 0) % $images->count());
    }

    /**
     * 找出超過字數上限的字幕。
     *
     * @return array<string, int> shot_id => 實際字數
     */
    private function overlongSubtitles(ScriptOutput $output): array
    {
        $overlong = [];

        foreach (array_values($output->shots) as $index => $shot) {
            $length = mb_strlen(trim($shot->subtitle));

            if ($length > self::MAX_SUBTITLE_CHARS) {
                $overlong['S'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = $length;
            }
        }

        return $overlong;
    }

    /** @param array<string, int> $overlong */
    private function overlongFeedback(array $overlong): string
    {
        $lines = [];

        foreach ($overlong as $shotId => $length) {
            $lines[] = sprintf('- %s.subtitle 共 %d 字，超過上限 %d 字，請精簡', $shotId, $length, self::MAX_SUBTITLE_CHARS);
        }

        return implode("\n", $lines);
    }

    /** @param array<string, int> $overlong */
    private function manualReason(ComplianceReport $report, array $overlong = []): string
    {
        $lines = ['腳本自動重寫 '.self::MAX_RETRIES." 次後仍不合規（規則版本 {$report->rulesVersion}），請手動修正字幕與文案後重新送審："];

        if ($report->profileBlocked) {
            $lines[] = '⛔ 此商品分類不開放製作影片：'.$report->profileBlockedReason;
        }

        foreach ($report->blocking() as $finding) {
            $lines[] = sprintf(
                '⛔ %s「%s」：%s%s',
                $finding->field,
                $finding->matched,
                $finding->message,
                filled($finding->suggestion) ? '　建議：'.$finding->suggestion : '',
            );
        }

        foreach ($report->unacknowledgedWarnings() as $finding) {
            $lines[] = sprintf('⚠️ %s「%s」：%s', $finding->field, $finding->matched, $finding->message);
        }

        foreach ($overlong as $shotId => $length) {
            $lines[] = sprintf('⚠️ %s 字幕 %d 字超過上限 %d 字，渲染時會折行蓋住畫面', $shotId, $length, self::MAX_SUBTITLE_CHARS);
        }

        return implode("\n", $lines);
    }
}
