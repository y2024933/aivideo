/**
 * Ken Burns 運鏡參數表。key 與 app/Enums/KenBurns.php 的 value 一對一（camelCase）。
 *
 * s = scale 的 [起, 迄]，x / y = translate 的 [起, 迄]（單位 %）。
 *
 * ⚠️ scale 上限刻意壓在 1.18–1.20：fit=contain 的方圖寬度已等於畫布寬 1080，
 * scale(1.18) 左右各裁約 97px，是「輕微推進」。不要用 1.3 以上，會切掉商品本體。
 */
export const KEN_BURNS = {
  none: { s: [1.0, 1.0], x: [0, 0], y: [0, 0] },
  zoomIn: { s: [1.0, 1.18], x: [0, 0], y: [0, 0] },
  zoomOut: { s: [1.18, 1.0], x: [0, 0], y: [0, 0] },
  panLeft: { s: [1.15, 1.15], x: [4, -4], y: [0, 0] },
  panRight: { s: [1.15, 1.15], x: [-4, 4], y: [0, 0] },
  panUp: { s: [1.15, 1.15], x: [0, 0], y: [4, -4] },
  panDown: { s: [1.15, 1.15], x: [0, 0], y: [-4, 4] },
  zoomInPanUp: { s: [1.02, 1.2], x: [0, 0], y: [3, -3] },
  zoomOutPanDown: { s: [1.2, 1.02], x: [0, 0], y: [-3, 3] },
};

/** 'auto' 的輪替序列，避免每鏡同一種運鏡看起來像幻燈片 */
const AUTO_CYCLE = ["zoomIn", "panLeft", "zoomOut", "panRight", "zoomInPanUp", "panUp"];

/**
 * 解析運鏡參數。'auto' 或未知值時依鏡次輪替。
 *
 * @param {string|null|undefined} kenBurns
 * @param {number} index 鏡次（0-based）
 */
export const resolveKenBurns = (kenBurns, index = 0) =>
  KEN_BURNS[kenBurns] ?? KEN_BURNS[AUTO_CYCLE[index % AUTO_CYCLE.length]];
