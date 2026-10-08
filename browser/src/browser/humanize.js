/**
 * 擬人化動作。
 *
 * 不是為了「騙過」偵測，而是避免最廉價的機器人特徵：毫秒級的連續動作、
 * 零延遲的表單填寫、固定間隔的請求。
 */
import { sleep } from '../lib/retry.js';

/** 隨機停頓（秒），預設 0.8–3.0 秒 */
export async function sleepJitter(min = 0.8, max = 3.0) {
  const lo = Math.max(0, Number(min) || 0);
  const hi = Math.max(lo, Number(max) || lo);
  const seconds = lo + Math.random() * (hi - lo);

  await sleep(Math.round(seconds * 1000));
}

/** 一個字一個字輸入，字距 60–180ms（真人打字的區間） */
export async function typeHuman(locator, text, options = {}) {
  const { minDelay = 60, maxDelay = 180 } = options;

  await locator.click();
  await sleepJitter(0.2, 0.6);

  for (const char of String(text)) {
    await locator.type(char, { delay: minDelay + Math.random() * (maxDelay - minDelay) });
  }

  await sleepJitter(0.3, 0.9);
}

/** 捲動到元素附近再動作，而不是直接 click 一個視窗外的東西 */
export async function scrollIntoViewHuman(locator) {
  await locator.scrollIntoViewIfNeeded().catch(() => {});
  await sleepJitter(0.3, 1.0);
}

/** 分段捲動頁面（觸發 lazy-load 的商品圖） */
export async function scrollPage(page, steps = 4) {
  for (let i = 0; i < steps; i += 1) {
    await page.mouse.wheel(0, 600 + Math.round(Math.random() * 400));
    await sleepJitter(0.4, 1.2);
  }
}

export default { sleepJitter, typeHuman, scrollIntoViewHuman, scrollPage };
