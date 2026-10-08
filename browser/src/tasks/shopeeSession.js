/**
 * 檢查登入 profile 還活著沒。
 *
 * ⚠️ 唯讀：只開一個頁面看有沒有被踢去登入頁，不點任何按鈕、不送任何表單。
 *    這是上架（P8）前的前置檢查 —— 與其在填表填到一半才發現登入過期，
 *    不如事前問一句。
 *
 * ⚠️ 不要把這個任務排成高頻 cron。每次檢查都是一次真實請求，一天打幾百次
 *    比不檢查更容易觸發風控。config 的 session_check_per_hour 預設 12。
 */
import config from '../config.js';
import { withPage, profileExists } from '../browser/pool.js';
import { sleepJitter } from '../browser/humanize.js';
import { ok, failed } from '../lib/result.js';

const SELLER_HOME = 'https://seller.shopee.tw/portal/settings/shop/profile';

async function checkOne(profile, jitter) {
  if (!(await profileExists(profile))) {
    return {
      profile,
      loggedIn: false,
      reason: '找不到 profile 目錄，這台機器還沒做過首次人工登入（npm run first-login）',
    };
  }

  try {
    return await withPage(profile, async (page) => {
      await page.goto(SELLER_HOME, { waitUntil: 'domcontentloaded' });
      await sleepJitter(jitter.min, jitter.max);

      const url = page.url();
      const loggedIn = !/\/(buyer\/)?login|\/account\/signin/u.test(url);

      return {
        profile,
        loggedIn,
        landedUrl: url,
        reason: loggedIn ? null : '被導向登入頁，session 已過期，需人工重新 SMS OTP 登入',
      };
    });
  } catch (error) {
    return { profile, loggedIn: false, reason: error?.message || String(error) };
  }
}

export async function shopeeSession(payload = {}) {
  const startedAt = Date.now();
  const jitter = payload.jitter || config.jitter;
  const profiles = Array.isArray(payload.profiles) && payload.profiles.length > 0
    ? payload.profiles
    : [config.shopee.sellerProfile];

  const sessions = [];

  for (const profile of profiles) {
    // 刻意序列化：同一個 user-data-dir 不能並行，不同 profile 並行也沒必要
    sessions.push(await checkOne(String(profile), jitter));
  }

  if (sessions.length === 0) {
    return failed('no_profiles', '沒有要檢查的 profile', Date.now() - startedAt);
  }

  return ok({ sessions }, 'dom', Date.now() - startedAt);
}

export default shopeeSession;
