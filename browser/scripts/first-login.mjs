#!/usr/bin/env node
/**
 * 首次人工登入蝦皮（建立 persistent profile）。
 *
 * ⚠️⚠️ 這支一定要在 macOS 原生跑 Node，不可以在 Docker 裡跑。
 *      Docker Desktop on macOS 沒有 X11 / Quartz 轉接，容器內的 GUI 視窗
 *      根本顯示不出來 —— 而 SMS OTP 必須人眼看、人手打，沒有視窗就沒辦法登入。
 *
 * ⚠️ 用 launchPersistentContext 而非 storageState：蝦皮的「記住這台裝置」靠
 *    IndexedDB 與 device fingerprint，storageState 只存 cookies + localStorage，
 *    會導致每次都要重打 OTP。
 *
 * ⚠️ profile 放 ~/.shopee-automation/profiles/，不要用 Chrome 的預設 profile 目錄
 *    （弄壞了連使用者自己的書籤與登入都一起沒）。
 *
 * 用法：
 *   cd browser && npm install && node scripts/first-login.mjs [profile名稱]
 *   預設 profile 名稱 shopee-seller。完成後依 README 的步驟 docker compose cp 進 volume。
 */
import os from 'node:os';
import path from 'node:path';
import fs from 'node:fs/promises';
import readline from 'node:readline/promises';
import { chromium } from 'rebrowser-playwright';
import { LAUNCH_ARGS, USER_AGENT, VIEWPORT } from '../src/browser/launchArgs.js';

const PROFILE = process.argv[2] || 'shopee-seller';
const ROOT = path.join(os.homedir(), '.shopee-automation', 'profiles');
const USER_DATA_DIR = path.join(ROOT, PROFILE);
const LOGIN_URL = 'https://shopee.tw/buyer/login';

async function main() {
  await fs.mkdir(USER_DATA_DIR, { recursive: true });

  console.log('');
  console.log('────────────────────────────────────────────────────────');
  console.log(` profile 目錄：${USER_DATA_DIR}`);
  console.log('────────────────────────────────────────────────────────');
  console.log('');
  console.log(' 1. 等一下會開啟一個真實的 Chrome 視窗');
  console.log(' 2. 請「手動」登入（帳密 + SMS OTP）');
  console.log(' 3. 登入時務必勾選「記住這台裝置」，否則下次還要 OTP');
  console.log(' 4. 登入完成並看到首頁後，回到這個終端機按 Enter');
  console.log('');
  console.log(' ⚠️ 不要在這個視窗裡做大量瀏覽或下單，它的唯一用途是建立 session。');
  console.log('');

  const context = await chromium.launchPersistentContext(USER_DATA_DIR, {
    channel: 'chrome',
    // ⚠️ 必須 false：要讓人看得到視窗才能輸入 OTP
    headless: false,
    args: LAUNCH_ARGS,
    userAgent: USER_AGENT,
    viewport: VIEWPORT,
    locale: 'zh-TW',
    timezoneId: 'Asia/Taipei',
  });

  const page = context.pages()[0] ?? (await context.newPage());
  await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });

  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  await rl.question('登入完成後按 Enter 繼續…');
  rl.close();

  const cookies = await context.cookies();
  const hasSession = cookies.some((cookie) => cookie.name === 'SPC_EC' || cookie.name === 'SPC_ST');

  console.log('');
  console.log(hasSession ? '✅ 偵測到登入 cookie，profile 已建立。' : '⚠️ 沒看到登入 cookie，可能尚未登入成功，請重跑一次。');
  console.log('');
  console.log('接下來（在專案根目錄執行）：');
  console.log('  docker compose --profile browser up -d browser');
  console.log(`  docker compose cp "${USER_DATA_DIR}" aivideo-browser:/profiles/${PROFILE}`);
  console.log('  docker compose --profile browser restart browser');
  console.log('');

  // 一定要正常 close，否則 profile 會留下 SingletonLock，之後容器內啟動會失敗
  await context.close();
}

main().catch((error) => {
  console.error('首次登入失敗：', error);
  process.exit(1);
});
