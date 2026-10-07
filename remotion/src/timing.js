/**
 * 時長計算的「單一來源」。
 *
 * 畫面（TransitionSeries）、音軌（Sequence from）、calculateMetadata 的總長度
 * 三者必須用同一組數字。歷史上這套算法被複製在 Root.jsx 與影片 component
 * 各寫一份，其中音軌那份漏掉了 overlap 扣減 → 配音每鏡累積落後（M1 bug）。
 *
 * PHP 端對應的公式在 app/Services/Llm/ShotDurationEstimator::fitToTarget()，
 * 兩邊改動必須同步（tests/Unit/Remotion/TimingParityTest.php 守著）。
 */

/** 轉場 overlap 長度（秒）。非 cut 的轉場會讓前後兩鏡重疊這麼久。 */
export const TRANSITION_DURATION_SEC = 0.5;

/** 單一鏡頭的幀數，至少 1 幀。 */
export const shotFrames = (s, fps) => Math.max(Math.round((s.durationSec ?? 5) * fps), 1);

/** 轉場 overlap 的幀數。 */
export const overlapFrames = (fps) => Math.round(TRANSITION_DURATION_SEC * fps);

/** 鏡頭的實際轉場：鏡頭自己的設定 > 全域設定 > crossfade。 */
export const resolveTransition = (s, g) => s.transition ?? g ?? "crossfade";

/**
 * 第 i 鏡「畫面」在整支時間軸上的起始 frame。
 *
 * ⚠️ 音軌必須用同一組值，否則配音每鏡累積落後 overlap（M1 bug）。
 *
 * @param {Array<{durationSec?: number, transition?: string}>} shots
 * @param {number} fps
 * @param {string} [globalTransition]
 * @returns {number[]}
 */
export function shotOffsets(shots, fps, globalTransition) {
  const overlap = overlapFrames(fps);
  const list = shots ?? [];
  const offsets = [];
  let cursor = 0;

  list.forEach((shot, i) => {
    offsets.push(cursor);
    cursor += shotFrames(shot, fps);

    // 最後一鏡後面沒有轉場；cut 不產生 overlap
    if (i < list.length - 1 && resolveTransition(shot, globalTransition) !== "cut") {
      cursor -= overlap;
    }
  });

  return offsets;
}

/** 總長度。與 shotOffsets 同源，不另寫公式。 */
export function totalFrames(shots, fps, globalTransition) {
  const list = shots ?? [];

  if (list.length === 0) {
    return 1;
  }

  const offsets = shotOffsets(list, fps, globalTransition);

  return Math.max(offsets[offsets.length - 1] + shotFrames(list[list.length - 1], fps), 1);
}
