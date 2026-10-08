/**
 * 任務內部的小重試（只處理「同一次瀏覽器操作偶發失敗」）。
 *
 * ⚠️ 真正的重試策略在 PHP 端（PlaywrightBrowserAutomation，10/30/90 秒 backoff）。
 * 這裡只做 1–2 次、秒級的重試，否則兩層重試相乘會把 rate limit 吃光。
 */

export const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * @param {() => Promise<any>} fn
 * @param {{attempts?: number, delayMs?: number, shouldRetry?: (error: Error) => boolean}} options
 */
export async function retry(fn, options = {}) {
  const attempts = options.attempts ?? 2;
  const delayMs = options.delayMs ?? 1500;
  const shouldRetry = options.shouldRetry ?? (() => true);

  let lastError;

  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      return await fn(attempt);
    } catch (error) {
      lastError = error;

      if (attempt === attempts || !shouldRetry(error)) {
        break;
      }

      await sleep(delayMs * attempt);
    }
  }

  throw lastError;
}

/** 讓任何 promise 都有上限，避免 Chromium 卡住時整個 queue 永久堵住 */
export async function withTimeout(promise, ms, message = '操作逾時') {
  let timer;

  try {
    return await Promise.race([
      promise,
      new Promise((_, reject) => {
        timer = setTimeout(() => reject(new Error(message)), ms);
      }),
    ]);
  } finally {
    clearTimeout(timer);
  }
}
