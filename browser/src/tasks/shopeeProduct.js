/**
 * 抓蝦皮商品。
 *
 * ──────────────────────────────────────────────────────────────────────────
 * 為什麼一定要開瀏覽器？（實測結論，不要再試純 HTTP）
 *
 *  1. 直接打 /api/v4/pdp/get_pc 回 {"error":90309999,"action_type":2}。
 *  2. 社群流傳的 `af-ac-enc-dat: null` 繞過法只能把 403 變成 200，body 仍是
 *     同一個錯誤碼 —— 它繞過的是 WAF，不是簽章驗證。
 *  3. 商品頁 HTML 是純 SPA shell，連 og:title 都沒有，解析 HTML 拿不到任何欄位。
 *
 * 結論：簽章必須由蝦皮自己的 JS 算，所以我們讓頁面正常載入，然後攔 response。
 * ──────────────────────────────────────────────────────────────────────────
 *
 * ⚠️ 用「未登入」的 profile。商品頁不需要登入就看得到 → 這條路徑零帳號風險。
 *    登入 profile 只給之後的上架用，兩者絕不混用。
 */
import config from '../config.js';
import { withPage } from '../browser/pool.js';
import { sleepJitter, scrollPage } from '../browser/humanize.js';
import * as artifacts from '../browser/artifacts.js';
import { ok, degraded, failed, needsManual } from '../lib/result.js';

/** 蝦皮自己回的業務錯誤碼（非 HTTP 狀態） */
class ShopeeApiError extends Error {
  constructor(code) {
    super(`get_pc 回傳錯誤碼 ${code}`);
    this.code = code;
  }
}

/** 這些頁面特徵代表「重跑也沒用，要人介入」 */
const MANUAL_MARKERS = [
  'verify/traffic',
  'verify/captcha',
  '/buyer/login',
];

/** 擋掉圖片／字型／影音以省頻寬。⚠️ 不會影響 XHR —— get_pc 是 fetch，不是 resource。 */
async function blockHeavyResources(page) {
  await page.route('**/*', (route) => {
    const type = route.request().resourceType();

    if (type === 'image' || type === 'font' || type === 'media') {
      return route.abort();
    }

    return route.continue();
  });
}

/**
 * @param {object} payload
 * @param {string} payload.url 商品 canonical url
 * @param {boolean} [payload.allowDomFallback]
 * @param {{min:number,max:number}} [payload.jitter]
 */
export async function shopeeProduct(payload = {}) {
  const startedAt = Date.now();
  const url = String(payload.url || '');
  const allowDomFallback = payload.allowDomFallback !== false;
  const jitter = payload.jitter || config.jitter;

  if (!url) {
    return failed('missing_url', '缺少商品網址', Date.now() - startedAt);
  }

  return withPage(config.shopee.anonymousProfile, async (page, context) => {
    const traceName = `shopee-product-${Date.now()}`;
    const tracing = await artifacts.startTracing(context, traceName);
    let domData = null;

    try {
      await blockHeavyResources(page);
      await sleepJitter(jitter.min, jitter.max);

      // ⚠️ 順序關鍵：waitForResponse 必須在 goto 之前掛好。
      //    反過來寫的話，get_pc 常常在 goto 回來之前就已經完成 → 永遠等不到。
      const waiter = page.waitForResponse(
        (response) => response.url().includes(config.shopee.pdpApiPath) && response.status() === 200,
        { timeout: config.timeouts.xhr },
      );

      await page.goto(url, { waitUntil: 'domcontentloaded' });

      if (MANUAL_MARKERS.some((marker) => page.url().includes(marker))) {
        const shot = await artifacts.screenshot(page, `${traceName}-manual`);

        return needsManual('verification_required', `被導向驗證／登入頁：${page.url()}`, Date.now() - startedAt, {
          screenshot: shot,
          trace: await artifacts.stopTracing(context, traceName, { keep: tracing }),
        });
      }

      let json;

      try {
        const response = await waiter;
        json = await response.json();
      } catch (error) {
        // XHR 沒攔到 → 走降級，不要直接失敗
        if (!allowDomFallback) {
          throw error;
        }

        domData = await extractFromDom(page, jitter);

        const shot = await artifacts.screenshot(page, `${traceName}-degraded`);
        const trace = await artifacts.stopTracing(context, traceName, { keep: tracing });

        if (!domData?.title) {
          return failed('xhr_missed_and_dom_empty', `沒攔到 get_pc，DOM 也撈不到標題：${error.message}`, Date.now() - startedAt, {
            screenshot: shot,
            trace,
          });
        }

        return degraded({ dom: domData }, 'xhr_missed', `沒攔到 get_pc（${error.message}），改用 DOM 解析`, Date.now() - startedAt, {
          screenshot: shot,
          trace,
        });
      }

      if (json?.error) {
        throw new ShopeeApiError(json.error);
      }

      await artifacts.stopTracing(context, traceName, { keep: false });

      // raw 整包回傳：PHP 端會存進 products.raw_payload，蝦皮改版時可回推新 schema
      return ok({ raw: json }, 'xhr', Date.now() - startedAt);
    } catch (error) {
      const shot = await artifacts.screenshot(page, `${traceName}-failed`);
      const trace = await artifacts.stopTracing(context, traceName, { keep: tracing });
      const extra = { screenshot: shot, trace };

      if (error instanceof ShopeeApiError) {
        // 90309999 = 簽章／風控；這類錯誤重跑通常一樣，交給人看
        return needsManual('shopee_api_error', `${error.message}（可能已被風控或商品下架）`, Date.now() - startedAt, extra);
      }

      return failed('scrape_failed', error?.message || String(error), Date.now() - startedAt, extra);
    }
  });
}

/**
 * DOM 降級解析。
 *
 * 只拿得到標題、價格與圖片 —— 評分、銷量、規格都在 SPA 的 React state 裡，
 * 沒有穩定選擇器。這就是 degraded 不能自動放行 checkpoint ① 的原因。
 */
async function extractFromDom(page, jitter = config.jitter) {
  try {
    // 先捲動觸發 lazy-load 的商品圖
    await scrollPage(page, 3);
    await sleepJitter(jitter.min, jitter.max);

    return await page.evaluate(() => {
      const text = (selectors) => {
        for (const selector of selectors) {
          const element = document.querySelector(selector);

          if (element?.textContent?.trim()) {
            return element.textContent.trim();
          }
        }

        return null;
      };

      // 蝦皮的 class 是編譯產生的亂碼，所以用「語意選擇器 + h1 退路」
      const title =
        text(['h1', '[data-sqe="name"]', 'div[role="heading"]']) ||
        document.title.replace(/\s*\|\s*蝦皮購物.*$/u, '').trim() ||
        null;

      const rawPrice = text(['[class*="product-price"]', '[data-sqe="price"]', 'div[aria-live="polite"]']);
      // 「$1,290 - $1,590」取第一個數字；去掉千分位後才 parseFloat
      const priceMatch = rawPrice ? String(rawPrice).replace(/,/gu, '').match(/(\d+(?:\.\d+)?)/u) : null;

      const images = [...document.querySelectorAll('img')]
        .map((img) => img.getAttribute('src') || img.getAttribute('data-src') || '')
        .filter((src) => src.includes('susercontent.com'))
        // 去掉 @resize_wNNN 後綴拿原圖；縮圖拉成 1080x1920 影片會糊成一團
        .map((src) => src.replace(/@resize_w\d+(_nl)?(\.\w+)?$/u, ''))
        .filter((src, index, all) => all.indexOf(src) === index)
        .slice(0, 12);

      return {
        title,
        price: priceMatch ? Number(priceMatch[1]) : null,
        images,
        url: window.location.href,
      };
    });
  } catch {
    return null;
  }
}

export default shopeeProduct;
