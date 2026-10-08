/**
 * 從任意網頁撈圖片網址（外站素材用，不含蝦皮）。
 *
 * 只回「網址」不回「檔案」：下載由 Laravel 的 ImageDownloader 做（它要雙寫 S3、
 * 算 width/height、記 license_status），在這裡下載等於把同一件事做兩次。
 */
import config from '../config.js';
import { withPage } from '../browser/pool.js';
import { sleepJitter, scrollPage } from '../browser/humanize.js';
import * as artifacts from '../browser/artifacts.js';
import { ok, failed } from '../lib/result.js';

/** 外站一律用獨立的匿名 profile，不要碰蝦皮的任何 profile */
const PROFILE = 'external-anon';

export async function externalImages(payload = {}) {
  const startedAt = Date.now();
  const url = String(payload.url || '');
  const minWidth = Number(payload.minWidth) || 600;
  const limit = Number(payload.limit) || 20;
  const jitter = payload.jitter || config.jitter;

  if (!url) {
    return failed('missing_url', '缺少網址', Date.now() - startedAt);
  }

  return withPage(PROFILE, async (page) => {
    const name = `external-images-${Date.now()}`;

    try {
      await page.goto(url, { waitUntil: 'domcontentloaded' });
      await sleepJitter(jitter.min, jitter.max);
      // lazy-load 的圖要捲到才會換上真正的 src
      await scrollPage(page, 5);

      const images = await page.evaluate((options) => {
        const absolute = (src) => {
          try {
            return new URL(src, window.location.href).toString();
          } catch {
            return null;
          }
        };

        return [...document.querySelectorAll('img')]
          .map((img) => ({
            src: absolute(img.currentSrc || img.getAttribute('src') || img.getAttribute('data-src') || ''),
            // naturalWidth 才是真實解析度；width 屬性是 CSS 顯示寬度
            width: img.naturalWidth || 0,
            height: img.naturalHeight || 0,
          }))
          .filter((item) => item.src && item.src.startsWith('http') && item.width >= options.minWidth)
          .filter((item, index, all) => all.findIndex((other) => other.src === item.src) === index)
          .sort((a, b) => b.width * b.height - a.width * a.height)
          .slice(0, options.limit);
      }, { minWidth, limit });

      return ok({ images, pageUrl: page.url() }, 'dom', Date.now() - startedAt);
    } catch (error) {
      return failed('external_images_failed', error?.message || String(error), Date.now() - startedAt, {
        screenshot: await artifacts.screenshot(page, `${name}-failed`),
      });
    }
  });
}

export default externalImages;
