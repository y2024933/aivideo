<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * 鏡頭時長估算。
 *
 * LLM 寫出來的 durationSec 常常不合理（字幕 20 字給 2 秒、整支加起來 70 秒），
 * 這裡用三條公式把它矯正回可讀、可對齊配音、總長符合需求的值。
 *
 * ⚠️ TRANSITION_OVERLAP_SEC 與 remotion/src/timing.js 的 TRANSITION_DURATION_SEC
 * 必須一致，fitToTarget() 的有效總長公式也必須與 timing.js::totalFrames() 同源，
 * 否則後端算出的秒數與 Lambda 實際渲染的長度會對不上。
 * tests/Unit/Remotion/TimingParityTest.php 守著這件事。
 */
final class ShotDurationEstimator
{
    private const CHARS_PER_SEC = 4.5;   // 中文字幕可讀速度（≈270 字/分，短影音節奏）
    private const PADDING_SEC = 0.8;
    private const MIN_SEC = 1.5;
    private const MAX_SEC = 6.0;

    /** 非 cut 轉場讓前後兩鏡重疊的秒數。與 remotion/src/timing.js 的 TRANSITION_DURATION_SEC 同值。 */
    public const TRANSITION_OVERLAP_SEC = 0.5;

    /** 誤差在這個比例內就不動 LLM 給的節奏（硬縮放反而會破壞刻意的長短對比） */
    private const TARGET_TOLERANCE = 0.15;

    /** 公式 1：字幕字數 → 最短可讀秒數，0.5 秒為單位向上取整 */
    public static function fromSubtitle(string $subtitle): float
    {
        // 標點與空白不佔閱讀時間，先去掉再算字數
        $chars = mb_strlen((string) preg_replace('/[\s\p{P}]+/u', '', $subtitle));

        return self::clamp(self::ceilHalf($chars / self::CHARS_PER_SEC + self::PADDING_SEC));
    }

    /**
     * 公式 2：有配音時，以配音長度為主，但不得短於字幕可讀時間。
     *
     * ⚠️ padding 必須「大於」TRANSITION_OVERLAP_SEC(0.5)。
     * Remotion 的配音音軌是對齊畫面 offset 的（M1 修正），而畫面時間軸每遇一次非 cut
     * 轉場就被壓縮 0.5 秒 —— 若 padding <= 0.5，第 i 鏡的配音尾端會跨進第 i+1 鏡的
     * 配音起點，退回成 commit eaa5145 修掉的「兩個人聲疊在一起」。
     * cut 不產生 overlap，所以只需要留一點呼吸空間。
     */
    public static function fromTts(float $ttsSeconds, string $subtitle, string $transition = 'crossfade'): float
    {
        $pad = $transition === 'cut' ? 0.3 : 0.6;   // 0.6 > overlap 0.5

        return min(max(self::ceilHalf($ttsSeconds + $pad), self::fromSubtitle($subtitle)), self::MAX_SEC);
    }

    /**
     * 公式 3：整體等比縮放到目標長度，誤差 >15% 時才動。
     *
     * @param  list<float>  $durations
     * @param  int  $nonCutTransitions  非 cut 的接縫數量（n-1 減去 cut 的個數）
     * @return list<float>
     */
    public static function fitToTarget(array $durations, int $targetSec, int $nonCutTransitions): array
    {
        $sum = array_sum($durations);

        if ($durations === [] || $targetSec <= 0 || $sum <= 0) {
            return array_values($durations);
        }

        $effective = self::effectiveSeconds($durations, $nonCutTransitions);

        if (abs($effective - $targetSec) / $targetSec <= self::TARGET_TOLERANCE) {
            return array_values($durations);
        }

        // 目標是「有效總長」而非單純總和，所以把被轉場吃掉的秒數加回去再分攤
        $factor = ($targetSec + $nonCutTransitions * self::TRANSITION_OVERLAP_SEC) / $sum;

        return array_values(array_map(fn (float $d) => self::clamp(self::roundHalf($d * $factor)), $durations));
    }

    /**
     * 有效總長（扣掉轉場重疊）。
     * 與 remotion/src/timing.js::totalFrames() 必須是同一條式子。
     *
     * @param  list<float>  $durations
     */
    public static function effectiveSeconds(array $durations, int $nonCutTransitions): float
    {
        return array_sum($durations) - $nonCutTransitions * self::TRANSITION_OVERLAP_SEC;
    }

    /** 向上取整到 0.5 秒 */
    private static function ceilHalf(float $seconds): float
    {
        return ceil($seconds * 2) / 2;
    }

    /** 四捨五入到 0.5 秒 */
    private static function roundHalf(float $seconds): float
    {
        return round($seconds * 2) / 2;
    }

    private static function clamp(float $seconds): float
    {
        return min(max($seconds, self::MIN_SEC), self::MAX_SEC);
    }
}
