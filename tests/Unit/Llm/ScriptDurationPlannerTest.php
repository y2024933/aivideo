<?php

declare(strict_types=1);

use App\Data\Llm\ScriptOutput;
use App\Data\Llm\ScriptShot;
use App\Services\Llm\ScriptDurationPlanner;
use App\Services\Llm\ShotDurationEstimator;

/**
 * ScriptDurationPlanner 把 LLM 給的 durationSeconds 校正成「看得完 + 總長對得上」的秒數。
 *
 * 單條公式的行為由 ShotDurationEstimatorTest 守著，這裡測的是 planner 自己的三件事：
 *   1. 取 max(LLM 值, 字幕可讀下限) —— LLM 幾乎永遠低估中文閱讀時間
 *   2. 算對「非 cut 接縫數」—— 最後一鏡的 transition 不產生接縫，算進去總長就會偏
 *   3. 把結果交給 fitToTarget()，總長落在目標 ±15%（除非撞到 1.5/6.0 的 clamp）
 */
function planOutput(array $rows, int $target): array
{
    return ScriptDurationPlanner::plan(
        new ScriptOutput(shots: array_map(
            fn (array $row) => new ScriptShot(durationSeconds: $row[0], subtitle: $row[1], transition: $row[2]),
            $rows,
        )),
        $target,
    );
}

/** 非 cut 的接縫數：最後一鏡的 transition 不算（它後面沒有下一鏡） */
function seamsOf(array $transitions): int
{
    return count(array_filter(array_slice($transitions, 0, -1), fn (string $t) => $t !== 'cut'));
}

// ─────────────────────────────────────────────────────────────
// 1. LLM 低估 → 被字幕可讀下限拉高
// ─────────────────────────────────────────────────────────────

it('raises durations the LLM underestimated to the subtitle readable floor', function () {
    // 18 字 / 4.5 + 0.8 padding = 4.8 秒，向上取 0.5 → 5.0；LLM 只給 2.0
    $subtitle = str_repeat('字', 18);

    expect(ShotDurationEstimator::fromSubtitle($subtitle))->toBe(5.0);

    // 兩鏡各 5.0、cut 不產生接縫 → 有效總長 10 秒，剛好等於目標，不觸發縮放
    expect(planOutput([[2.0, $subtitle, 'cut'], [2.0, $subtitle, 'cut']], 10))->toBe([5.0, 5.0]);
});

it('keeps a generous llm duration when it already exceeds the floor', function () {
    // LLM 給 5.0 而字幕只需 1.5 → 以 LLM 的節奏為準（刻意的長鏡不該被壓掉）
    expect(planOutput([[5.0, '短', 'cut'], [5.0, '短', 'cut']], 10))->toBe([5.0, 5.0]);
});

it('mixes per-shot floors instead of applying one global value', function () {
    // 長短字幕混排時每鏡各自取自己的下限，不是整份套同一個數
    $plan = planOutput([[1.0, str_repeat('字', 18), 'cut'], [1.0, '短', 'cut'], [4.5, '短', 'cut']], 11);

    expect($plan)->toBe([5.0, 1.5, 4.5]);
});

// ─────────────────────────────────────────────────────────────
// 2. 接縫數：最後一鏡的 transition 不算
// ─────────────────────────────────────────────────────────────

it('ignores the last shot transition when counting seams', function () {
    $rows = fn (array $transitions) => array_map(fn (string $t) => [3.0, '短', $t], $transitions);

    // 全 cut → 0 條接縫；factor = 22/15 → 每鏡 4.5
    $allCut = planOutput($rows(['cut', 'cut', 'cut', 'cut', 'cut']), 22);

    // 只把「最後一鏡」改成 crossfade：它後面沒有下一鏡，接縫數仍是 0 → 結果必須一樣
    expect(planOutput($rows(['cut', 'cut', 'cut', 'cut', 'crossfade']), 22))->toBe($allCut)
        ->and($allCut)->toBe([4.5, 4.5, 4.5, 4.5, 4.5]);

    // 對照組：把前 4 個（真正的接縫）改成 crossfade 就一定會變
    // factor = (22 + 4 x 0.5) / 15 → 每鏡 5.0
    expect(planOutput($rows(['crossfade', 'crossfade', 'crossfade', 'crossfade', 'cut']), 22))
        ->toBe([5.0, 5.0, 5.0, 5.0, 5.0])
        ->not->toBe($allCut);
});

it('counts only non-cut seams in a mixed script', function () {
    $transitions = ['crossfade', 'cut', 'slideLeft', 'cut', 'crossfade'];

    expect(seamsOf($transitions))->toBe(2);

    $plan = planOutput(array_map(fn (string $t) => [3.0, '短', $t], $transitions), 22);

    // 有效總長要用「2 條接縫」去算才會落在目標 ±15%
    expect(abs(ShotDurationEstimator::effectiveSeconds($plan, 2) - 22) / 22)->toBeLessThanOrEqual(0.15)
        // 用錯的接縫數（把最後一鏡也算進去 = 3）就會偏掉，這是本測試的意義
        ->and(ShotDurationEstimator::effectiveSeconds($plan, 3))
        ->toBe(ShotDurationEstimator::effectiveSeconds($plan, 2) - 0.5);
});

// ─────────────────────────────────────────────────────────────
// 3. 總長落在目標 ±15%
// ─────────────────────────────────────────────────────────────

it('lands the effective total within 15% of the target', function (int $shots, int $target) {
    $transitions = array_map(fn (int $i) => $i % 3 === 0 ? 'cut' : 'crossfade', range(1, $shots));
    $plan = planOutput(array_map(fn (string $t) => [2.0, '通勤整路只剩引擎聲', $t], $transitions), $target);

    expect($plan)->toHaveCount($shots)
        ->and(abs(ShotDurationEstimator::effectiveSeconds($plan, seamsOf($transitions)) - $target) / $target)
        ->toBeLessThanOrEqual(0.15);
})->with([
    '3 鏡 / 15 秒' => [3, 15],
    '5 鏡 / 20 秒' => [5, 20],
    '5 鏡 / 30 秒' => [5, 30],
    '6 鏡 / 30 秒' => [6, 30],
    '8 鏡 / 30 秒' => [8, 30],
    '8 鏡 / 45 秒' => [8, 45],
]);

it('shrinks an over-long script down toward the target', function () {
    // 8 鏡都頂到 MAX_SEC(6.0) = 48 秒原始總長，要壓進 30 秒
    $plan = planOutput(array_fill(0, 8, [6.0, '短', 'crossfade']), 30);

    expect($plan)->toBe(array_fill(0, 8, 4.0))
        ->and(ShotDurationEstimator::effectiveSeconds($plan, 7))->toBe(28.5)
        ->and(abs(28.5 - 30) / 30)->toBeLessThanOrEqual(0.15);
});

// ─────────────────────────────────────────────────────────────
// 4. 邊界
// ─────────────────────────────────────────────────────────────

it('cannot stretch a single shot past the 6 second cap', function () {
    // 1 鏡 30 秒是做不到的 —— clamp 比目標長度優先，寧可短也不要一鏡 30 秒
    expect(planOutput([[2.0, '短', 'cut']], 30))->toBe([6.0]);
});

it('leaves a script that already hits the target untouched', function () {
    // 6 鏡 x 6.0 全 cut = 36 秒，目標 36 → 誤差 0，不動
    expect(planOutput(array_fill(0, 6, [6.0, '短', 'cut']), 36))->toBe(array_fill(0, 6, 6.0));
});

it('keeps every planned duration on the half-second grid inside the clamp', function () {
    foreach ([12, 20, 30, 45, 60] as $target) {
        foreach (planOutput(array_fill(0, 8, [1.0, '短', 'crossfade']), $target) as $value) {
            expect($value)->toBeGreaterThanOrEqual(1.5)->toBeLessThanOrEqual(6.0)
                ->and(fmod($value, 0.5))->toBe(0.0);
        }
    }
});

it('returns an empty plan for an empty script', function () {
    expect(ScriptDurationPlanner::plan(new ScriptOutput(), 30))->toBe([]);
});

it('skips scaling when the target is not set', function () {
    // video_length_seconds 為 0／null 時 GenerateScriptJob 會 fallback 到 30，
    // 但 planner 本身收到 0 必須原樣回傳校正後的下限，不可除以 0
    expect(planOutput([[2.0, str_repeat('字', 18), 'cut'], [1.0, '短', 'cut']], 0))->toBe([5.0, 1.5]);
});

it('returns a packed list indexed from zero', function () {
    // GenerateScriptJob 用 $durations[$index] 取值，非連續索引會取到 null
    $plan = planOutput([[2.0, '短', 'cut'], [2.0, '短', 'cut'], [2.0, '短', 'cut']], 20);

    expect(array_keys($plan))->toBe([0, 1, 2])
        ->and(array_is_list($plan))->toBeTrue();
});
