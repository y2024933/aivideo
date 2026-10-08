/**
 * 瀏覽器 context 池。
 *
 * ⚠️ 用 launchPersistentContext(userDataDir) 而不是 browser.newContext({storageState})。
 *    storageState 只存 cookies + localStorage，但蝦皮的「記住這台裝置」靠的是
 *    IndexedDB 與 device fingerprint —— 用 storageState 的話每次登入都要重打 SMS OTP，
 *    而 OTP 必須人工輸入，等於整條自動化斷掉。
 *
 * ⚠️ 同一個 user-data-dir 不能同時被兩個 browser 開啟（Chrome 用 SingletonLock，
 *    硬開會把 profile 弄壞）→ per-profile mutex。
 *
 * ⚠️ 不要用 Chrome 的預設 profile 目錄（~/Library/Application Support/Google/Chrome）。
 *    一旦自動化把它弄壞，使用者自己的瀏覽器書籤與登入也一起沒了。
 */
import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'rebrowser-playwright';
import config from '../config.js';
import { contextOptions } from './launchArgs.js';

/** profile 名稱 → { context, closing } */
const contexts = new Map();
/** profile 名稱 → Promise chain（mutex） */
const locks = new Map();

const safeName = (profile) => String(profile || 'default').replace(/[^a-zA-Z0-9._-]/g, '_');

export function profilePath(profile) {
  return path.join(config.profilesDir, safeName(profile));
}

/**
 * per-profile mutex：同一個 profile 的工作排成一條鏈。
 * @param {string} profile
 * @param {() => Promise<any>} fn
 */
export function withProfileLock(profile, fn) {
  const key = safeName(profile);
  const previous = locks.get(key) ?? Promise.resolve();
  // 前一個工作失敗也要讓鏈繼續，否則整個 profile 永久鎖死
  const current = previous.catch(() => {}).then(fn);

  locks.set(
    key,
    current.catch(() => {}),
  );

  return current;
}

/**
 * 取得（必要時啟動）persistent context。
 * @param {string} profile
 * @param {object} overrides
 */
export async function getContext(profile, overrides = {}) {
  const key = safeName(profile);
  const existing = contexts.get(key);

  if (existing) {
    return existing;
  }

  const userDataDir = profilePath(key);
  await fs.mkdir(userDataDir, { recursive: true });

  const context = await chromium.launchPersistentContext(userDataDir, contextOptions(overrides));

  context.setDefaultNavigationTimeout(config.timeouts.navigation);
  context.setDefaultTimeout(config.timeouts.navigation);

  // 瀏覽器被外部關掉（崩潰、OOM）時要把快取清掉，否則下一次任務拿到死掉的 context
  context.on('close', () => {
    if (contexts.get(key) === context) {
      contexts.delete(key);
    }
  });

  contexts.set(key, context);

  return context;
}

/** 開一個新 page 並確保用完一定關掉（page 洩漏會持續吃記憶體直到容器 OOM） */
export async function withPage(profile, fn, overrides = {}) {
  return withProfileLock(profile, async () => {
    const context = await getContext(profile, overrides);
    const page = await context.newPage();

    try {
      return await fn(page, context);
    } finally {
      await page.close().catch(() => {});
    }
  });
}

export async function closeContext(profile) {
  const key = safeName(profile);
  const context = contexts.get(key);

  if (!context) {
    return;
  }

  contexts.delete(key);
  await context.close().catch(() => {});
}

export async function closeAll() {
  await Promise.all([...contexts.keys()].map((key) => closeContext(key)));
}

/** profile 目錄存在且不是空的 → 曾經登入過（真正有沒有效要靠 shopeeSession 任務確認） */
export async function profileExists(profile) {
  try {
    const entries = await fs.readdir(profilePath(profile));

    return entries.length > 0;
  } catch {
    return false;
  }
}

export default { getContext, withPage, withProfileLock, closeContext, closeAll, profilePath, profileExists };
