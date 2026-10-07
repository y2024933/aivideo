/**
 * timing.js 的 smoke check。
 *
 * remotion/ 沒有安裝 test runner（刻意：這個 package 只為了 bundle 存在，
 * 多裝 vitest/jest 會把 Lambda bundle 的依賴樹弄髒），所以用 node:assert 直接跑。
 *
 * 執行：cd remotion && node scripts/check-timing.mjs
 */
import assert from "node:assert/strict";
import { shotFrames, shotOffsets, totalFrames } from "../src/timing.js";

const fps = 30;
const three = [{ durationSec: 4 }, { durationSec: 4 }, { durationSec: 4 }];

// 非 cut 轉場：每接縫扣 15 幀 overlap
assert.deepEqual(shotOffsets(three, fps, "crossfade"), [0, 105, 210], "crossfade offsets");

// 全 cut：不扣 overlap
assert.deepEqual(
  shotOffsets(three.map((s) => ({ ...s, transition: "cut" })), fps, "crossfade"),
  [0, 120, 240],
  "cut offsets",
);

// 全域 cut 也一樣
assert.deepEqual(shotOffsets(three, fps, "cut"), [0, 120, 240], "global cut offsets");

// 總長 = 末項 offset + 末鏡長度
assert.equal(totalFrames(three, fps, "crossfade"), 210 + 120, "crossfade total");
assert.equal(totalFrames(three, fps, "cut"), 240 + 120, "cut total");

// 混合：第 1 接縫 cut、第 2 接縫 crossfade
const mixed = [{ durationSec: 4, transition: "cut" }, { durationSec: 4 }, { durationSec: 4 }];
assert.deepEqual(shotOffsets(mixed, fps, "crossfade"), [0, 120, 225], "mixed offsets");
assert.equal(totalFrames(mixed, fps, "crossfade"), 225 + 120, "mixed total");

// 邊界
assert.equal(shotFrames({ durationSec: 0 }, fps), 1, "至少 1 幀");
assert.equal(shotFrames({}, fps), 150, "durationSec 缺省為 5 秒");
assert.deepEqual(shotOffsets([], fps, "crossfade"), [], "空陣列");
assert.equal(totalFrames([], fps, "crossfade"), 1, "空陣列總長至少 1");
assert.deepEqual(shotOffsets([{ durationSec: 3 }], fps, "crossfade"), [0], "單鏡不扣 overlap");
assert.equal(totalFrames([{ durationSec: 3 }], fps, "crossfade"), 90, "單鏡總長");

console.log("check-timing.mjs OK");
