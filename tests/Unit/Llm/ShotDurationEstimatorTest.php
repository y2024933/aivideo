<?php

declare(strict_types=1);

use App\Services\Llm\ShotDurationEstimator;

// --- 公式 1：字幕字數 → 可讀秒數 ---

it('estimates readable seconds from subtitle length', function () {
    // 11 個字 / 4.5 + 0.8 padding = 3.24 秒，向上取整到 0.5 → 3.5
    expect(ShotDurationEstimator::fromSubtitle('降噪開啟後世界瞬間安靜'))->toBe(3.5);
});

it('ignores whitespace and punctuation when counting characters', function () {
    expect(ShotDurationEstimator::fromSubtitle('降噪開啟後 世界瞬間安靜。'))
        ->toBe(ShotDurationEstimator::fromSubtitle('降噪開啟後世界瞬間安靜'));
});

it('clamps empty subtitles to the minimum', function () {
    expect(ShotDurationEstimator::fromSubtitle(''))->toBe(1.5)
        ->and(ShotDurationEstimator::fromSubtitle('  ，。！'))->toBe(1.5);
});

it('clamps very long subtitles to the maximum', function () {
    expect(ShotDurationEstimator::fromSubtitle(str_repeat('字', 50)))->toBe(6.0);
});

it('rounds up to half seconds', function () {
    foreach ([1, 7, 13, 20, 26] as $chars) {
        $value = ShotDurationEstimator::fromSubtitle(str_repeat('字', $chars));
        expect(fmod($value, 0.5))->toBe(0.0);
    }
});

// --- 公式 2：配音長度 → 鏡頭長度（M1 的「音軌不互相重疊」保證）---

it('leaves room for the transition overlap so voiceovers never collide', function () {
    // 畫面時間軸每遇一次非 cut 轉場就壓縮 0.5 秒；padding 必須 > 0.5 才不會讓
    // 第 i 鏡的配音尾端蓋到第 i+1 鏡的配音起點。
    expect(ShotDurationEstimator::fromTts(3.2, '短', 'crossfade'))
        ->toBeGreaterThanOrEqual(3.2 + ShotDurationEstimator::TRANSITION_OVERLAP_SEC);
});

it('uses a smaller padding for cut transitions', function () {
    // cut 不產生 overlap，只留 0.3 秒呼吸空間：3.2 + 0.3 = 3.5
    expect(ShotDurationEstimator::fromTts(3.2, '短', 'cut'))->toBe(3.5)
        ->and(ShotDurationEstimator::fromTts(3.2, '短', 'cut'))
        ->toBeLessThan(ShotDurationEstimator::fromTts(3.2, '短', 'crossfade'));
});

it('never drops below the subtitle readable time', function () {
    // 配音只有 0.5 秒但字幕 20 字（需 5.5 秒），以字幕為準
    expect(ShotDurationEstimator::fromTts(0.5, str_repeat('字', 20)))
        ->toBe(ShotDurationEstimator::fromSubtitle(str_repeat('字', 20)));
});

it('caps tts-driven durations at the maximum', function () {
    expect(ShotDurationEstimator::fromTts(20.0, '短'))->toBe(6.0);
});

// --- 公式 3：整體縮放到目標長度 ---

it('leaves durations untouched when already within tolerance', function () {
    $durations = [3.0, 3.0, 3.0, 3.0, 3.0];

    // 有效長度 15 - 4 x 0.5 = 13 秒，目標 13 秒
    expect(ShotDurationEstimator::effectiveSeconds($durations, 4))->toBe(13.0)
        ->and(ShotDurationEstimator::fitToTarget($durations, 13, 4))->toBe($durations)
        // 誤差 14% 仍在容忍範圍內，不動 LLM 給的節奏
        ->and(ShotDurationEstimator::fitToTarget($durations, 15, 4))->toBe($durations);
});

it('scales durations so the effective length lands within 15% of the target', function () {
    $scaled = ShotDurationEstimator::fitToTarget([4.0, 4.0, 4.0, 4.0, 4.0, 4.0], 30, 5);

    expect(abs(ShotDurationEstimator::effectiveSeconds($scaled, 5) - 30) / 30)->toBeLessThanOrEqual(0.15);
});

it('scales down an over-long script', function () {
    $scaled = ShotDurationEstimator::fitToTarget([6.0, 6.0, 6.0, 6.0, 6.0, 6.0, 6.0, 6.0], 30, 7);

    expect(abs(ShotDurationEstimator::effectiveSeconds($scaled, 7) - 30) / 30)->toBeLessThanOrEqual(0.15)
        ->and(array_sum($scaled))->toBeLessThan(48.0);
});

it('keeps every scaled duration inside the min/max clamp and on a half-second grid', function () {
    $scaled = ShotDurationEstimator::fitToTarget([1.0, 2.0, 9.0, 4.0], 20, 3);

    foreach ($scaled as $value) {
        expect($value)->toBeGreaterThanOrEqual(1.5)->toBeLessThanOrEqual(6.0)
            ->and(fmod($value, 0.5))->toBe(0.0);
    }
});

it('returns the input untouched for degenerate cases', function () {
    expect(ShotDurationEstimator::fitToTarget([], 30, 0))->toBe([])
        ->and(ShotDurationEstimator::fitToTarget([3.0], 0, 0))->toBe([3.0])
        ->and(ShotDurationEstimator::fitToTarget([0.0, 0.0], 30, 1))->toBe([0.0, 0.0]);
});
