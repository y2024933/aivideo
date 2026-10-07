<?php

declare(strict_types=1);

use App\Services\Llm\ShotDurationEstimator;

/**
 * 守住「PHP 與 JS 兩邊的時長公式同源」。
 *
 * 理想做法是在測試裡直接 `node -e` import remotion/src/timing.js 比對，但 app 容器
 * 沒有裝 node（pest 是在容器內跑的），所以改成兩層防護：
 *   1. 直接讀 timing.js 原始碼，斷言常數值與關鍵邏輯（overlap 扣減）還在
 *   2. 在 PHP 重寫一份 shotOffsets / totalFrames，與 ShotDurationEstimator 對帳
 * JS 那一側的數值驗證由 remotion/scripts/check-timing.mjs 負責（`npm run check-timing`），
 * 本檔案的期望值刻意與該腳本寫成同一組，兩邊對不上就會有一邊紅。
 */
function timingJsSource(): string
{
    $path = base_path('remotion/src/timing.js');

    expect(file_exists($path))->toBeTrue('remotion/src/timing.js 不存在');

    return (string) file_get_contents($path);
}

/** timing.js::shotOffsets() 的 PHP 對照實作 */
function jsShotOffsets(array $durations, int $fps, array $transitions, string $globalTransition): array
{
    $overlap = (int) round(ShotDurationEstimator::TRANSITION_OVERLAP_SEC * $fps);
    $offsets = [];
    $cursor = 0;

    foreach ($durations as $i => $durationSec) {
        $offsets[] = $cursor;
        $cursor += max((int) round($durationSec * $fps), 1);

        if ($i < count($durations) - 1 && ($transitions[$i] ?? $globalTransition) !== 'cut') {
            $cursor -= $overlap;
        }
    }

    return $offsets;
}

/** timing.js::totalFrames() 的 PHP 對照實作 */
function jsTotalFrames(array $durations, int $fps, array $transitions, string $globalTransition): int
{
    if ($durations === []) {
        return 1;
    }

    $offsets = jsShotOffsets($durations, $fps, $transitions, $globalTransition);

    return max(end($offsets) + max((int) round(end($durations) * $fps), 1), 1);
}

it('keeps the transition overlap constant in sync between PHP and timing.js', function () {
    preg_match('/TRANSITION_DURATION_SEC\s*=\s*([\d.]+)/', timingJsSource(), $m);

    expect($m[1] ?? null)->not->toBeNull('timing.js 找不到 TRANSITION_DURATION_SEC')
        ->and((float) $m[1])->toBe(ShotDurationEstimator::TRANSITION_OVERLAP_SEC);
});

it('still subtracts the overlap in shotOffsets (M1 regression guard)', function () {
    // 這一行被刪掉就等於 M1 bug 回來了：配音會每鏡累積落後 overlap
    expect(timingJsSource())->toContain('cursor -= overlap');
});

it('matches the expected offsets from remotion/scripts/check-timing.mjs', function () {
    $durations = [4.0, 4.0, 4.0];

    expect(jsShotOffsets($durations, 30, [], 'crossfade'))->toBe([0, 105, 210])
        ->and(jsShotOffsets($durations, 30, [], 'cut'))->toBe([0, 120, 240])
        ->and(jsTotalFrames($durations, 30, [], 'crossfade'))->toBe(330)
        ->and(jsTotalFrames($durations, 30, [], 'cut'))->toBe(360);
});

it('agrees with ShotDurationEstimator::effectiveSeconds for the same shot list', function () {
    $durations = [3.5, 4.0, 2.5, 5.0, 3.0];
    $fps = config('video.fps', 30);

    // 全部非 cut → 4 個接縫
    expect((float) (jsTotalFrames($durations, $fps, [], 'crossfade') / $fps))
        ->toBe(ShotDurationEstimator::effectiveSeconds($durations, 4));

    // 第 2 個接縫是 cut → 3 個非 cut 接縫
    expect((float) (jsTotalFrames($durations, $fps, [1 => 'cut'], 'crossfade') / $fps))
        ->toBe(ShotDurationEstimator::effectiveSeconds($durations, 3));
});

it('lands on the target length after fitToTarget', function () {
    $fps = config('video.fps', 30);
    $scaled = ShotDurationEstimator::fitToTarget([4.0, 4.0, 4.0, 4.0, 4.0, 4.0], 30, 5);

    // fitToTarget 算出來的有效秒數 == Remotion 實際會渲染的總長
    expect((float) (jsTotalFrames($scaled, $fps, [], 'crossfade') / $fps))
        ->toBe(ShotDurationEstimator::effectiveSeconds($scaled, 5))
        ->toBeGreaterThanOrEqual(30 * 0.85)
        ->toBeLessThanOrEqual(30 * 1.15);
});

it('aligns the voiceover track with its own shot (M1 fix)', function () {
    // 6 鏡 crossfade @30fps：修正前音軌是純累加 [0,120,240,360,480,600]，
    // 畫面卻是 [0,105,210,315,420,525] —— 最後一鏡的配音晚了 75 幀（2.5 秒）。
    $durations = array_fill(0, 6, 4.0);

    expect(jsShotOffsets($durations, 30, [], 'crossfade'))->toBe([0, 105, 210, 315, 420, 525]);
});

it('keeps config/video.php in sync with the Root.jsx composition', function () {
    // config/video.php 的 docblock 要求 fps / canvas 與 Root.jsx 一致，
    // 不一致會讓後端估算的秒數與 Lambda 實際輸出的長度對不上
    $root = (string) file_get_contents(base_path('remotion/src/Root.jsx'));

    preg_match('/fps=\{(\d+)\}/', $root, $fps);
    preg_match('/width=\{(\d+)\}/', $root, $width);
    preg_match('/height=\{(\d+)\}/', $root, $height);

    expect((int) ($fps[1] ?? 0))->toBe((int) config('video.fps'))
        ->and((int) ($width[1] ?? 0))->toBe((int) config('video.canvas.width'))
        ->and((int) ($height[1] ?? 0))->toBe((int) config('video.canvas.height'));
});

it('keeps the build tag in sync between PHP and ProductVideo.jsx', function () {
    $jsx = (string) file_get_contents(base_path('remotion/src/ProductVideo.jsx'));

    $expected = (new ReflectionClass(App\Services\RemotionVideoEditor::class))->getConstant('EXPECTED_BUILD_TAG');

    expect($jsx)->toContain("BUILD_TAG = \"{$expected}\"");
});

it('keeps the ken burns keys in sync with the PHP enum', function () {
    $js = (string) file_get_contents(base_path('remotion/src/kenBurns.js'));

    // 'auto' 是 PHP 專屬的「輪替」指令，JS 端用 AUTO_CYCLE 處理，不在 KEN_BURNS 表裡
    foreach (App\Enums\KenBurns::cases() as $case) {
        if ($case === App\Enums\KenBurns::Auto) {
            continue;
        }

        expect($js)->toContain("{$case->value}:");
    }
});
