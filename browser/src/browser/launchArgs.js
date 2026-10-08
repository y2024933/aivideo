/**
 * 瀏覽器啟動參數與指紋。
 *
 * ⚠️ 每一條都是實測得出的，不要「順手優化」：
 *
 *  1. channel: 'chrome' —— 用系統安裝的真實 Chrome，不是 Playwright 的 bundled
 *     chromium。bundled 版的 UA、codec 清單與 navigator.plugins 都跟真實 Chrome 不同。
 *
 *  2. --disable-blink-features=AutomationControlled —— 拔掉 navigator.webdriver。
 *
 *  3. ⚠️ 絕對不要加 --single-process。實測會崩：
 *        Cannot use V8 Proxy resolver in single process mode
 *     而且崩的方式是「頁面載入成功但 DOM 輸出 0 bytes」，看起來像蝦皮改版，
 *     會浪費好幾小時往錯的方向查。
 *
 *  4. 固定指紋、不輪替。同一個 profile 每次換 UA／視窗大小，對平台來說是
 *     「同一台裝置的瀏覽器一直在變」—— 比固定指紋可疑得多。
 */

export const USER_AGENT =
  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36';

export const VIEWPORT = { width: 1440, height: 900 };

export const LAUNCH_ARGS = [
  '--disable-blink-features=AutomationControlled',
  '--disable-features=IsolateOrigins,site-per-process,Translate',
  '--no-first-run',
  '--no-default-browser-check',
  '--disable-background-timer-throttling',
  '--disable-backgrounding-occluded-windows',
  '--disable-renderer-backgrounding',
  '--disable-dev-shm-usage',
  '--window-size=1440,900',
  // ⚠️ 不要加 '--single-process'
];

/** launchPersistentContext 的共用選項 */
export function contextOptions(overrides = {}) {
  return {
    channel: 'chrome',
    headless: true,
    args: LAUNCH_ARGS,
    userAgent: USER_AGENT,
    viewport: VIEWPORT,
    locale: 'zh-TW',
    timezoneId: 'Asia/Taipei',
    // 台北市中心，與 timezone/locale 一致。三者不一致本身就是一個偵測訊號。
    geolocation: { latitude: 25.033, longitude: 121.5654 },
    permissions: [],
    colorScheme: 'light',
    deviceScaleFactor: 2,
    acceptDownloads: false,
    ignoreHTTPSErrors: false,
    extraHTTPHeaders: {
      'Accept-Language': 'zh-TW,zh;q=0.9,en;q=0.8',
    },
    ...overrides,
  };
}

export default { USER_AGENT, VIEWPORT, LAUNCH_ARGS, contextOptions };
