/**
 * 失敗時的除錯素材：全頁截圖 + Playwright trace。
 *
 * 「為什麼抓不到」幾乎只能從當下那張截圖看出來（人機驗證？地區限制？改版？），
 * 所以失敗路徑一定要留下東西。成功時不留 —— trace 一支好幾 MB，磁碟很快就滿。
 *
 * 這個目錄是暫存，PHP 端會下載後搬到 S3（容器重建就沒了）。
 */
import fs from 'node:fs/promises';
import path from 'node:path';
import config from '../config.js';

const stamp = () => new Date().toISOString().replace(/[:.]/g, '-');

export async function ensureDir() {
  await fs.mkdir(config.artifactsDir, { recursive: true });
}

/** 開始錄 trace。失敗不可以中斷任務 —— 除錯工具壞了不代表任務要失敗。 */
export async function startTracing(context, name) {
  try {
    await context.tracing.start({ name, screenshots: true, snapshots: true, sources: false });

    return true;
  } catch {
    return false;
  }
}

/**
 * 停止錄製並寫檔。
 * @returns {Promise<string|null>} 相對於 artifactsDir 的檔名
 */
export async function stopTracing(context, name, { keep = false } = {}) {
  try {
    if (!keep) {
      // 成功的任務不留 trace（一支好幾 MB）
      await context.tracing.stop();

      return null;
    }

    await ensureDir();
    const file = `${name}-${stamp()}.zip`;
    await context.tracing.stop({ path: path.join(config.artifactsDir, file) });

    return file;
  } catch {
    return null;
  }
}

/** 全頁截圖（fullPage：驗證碼有時在頁面下方） */
export async function screenshot(page, name) {
  try {
    await ensureDir();
    const file = `${name}-${stamp()}.png`;
    await page.screenshot({ path: path.join(config.artifactsDir, file), fullPage: true });

    return file;
  } catch {
    return null;
  }
}

/** 只允許讀 artifactsDir 底下的檔案 —— 這個 endpoint 是給 PHP 拉檔用的，不能變成任意讀檔 */
export function resolveArtifact(relativePath) {
  const target = path.resolve(config.artifactsDir, String(relativePath || ''));
  const root = path.resolve(config.artifactsDir);

  if (target !== root && !target.startsWith(root + path.sep)) {
    return null;
  }

  return target;
}

export default { startTracing, stopTracing, screenshot, resolveArtifact, ensureDir };
