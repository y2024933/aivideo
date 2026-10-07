<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Data\Llm\ScriptOutput;
use App\Data\Llm\ScriptShot;

/**
 * 把 LLM 給的 durationSeconds 校正成「看得完、加起來等於目標長度」的秒數。
 *
 * LLM 幾乎永遠低估中文閱讀時間（14 個字給 2 秒，實際要 4 秒），所以先用字幕字數
 * 取下限，再整體縮放到目標長度。配音長度要等 TTS 完成才知道，那是 P6 的事。
 */
final class ScriptDurationPlanner
{
    /** @return list<float> 與 $output->shots 同序 */
    public static function plan(ScriptOutput $output, int $targetSeconds): array
    {
        $durations = array_map(
            fn (ScriptShot $shot) => max($shot->durationSeconds, ShotDurationEstimator::fromSubtitle($shot->subtitle)),
            $output->shots,
        );

        return ShotDurationEstimator::fitToTarget(array_values($durations), $targetSeconds, self::nonCutTransitions($output));
    }

    /** 轉場是「切到下一鏡」用的，最後一鏡的 transition 不產生接縫 */
    private static function nonCutTransitions(ScriptOutput $output): int
    {
        $seams = array_slice(array_map(fn (ScriptShot $shot) => $shot->transition, $output->shots), 0, -1);

        return count(array_filter($seams, fn (string $transition) => $transition !== 'cut'));
    }
}
