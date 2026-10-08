/**
 * 服務設定。全部從環境變數讀，沒有設定檔 —— 這個容器唯一的狀態是 profiles volume。
 */
import path from 'node:path';

const num = (value, fallback) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

export const config = {
  port: num(process.env.PORT, 3031),
  host: process.env.HOST || '0.0.0.0',

  // 沒設 token 就不檢查（僅限本機實驗）。docker-compose 一定會帶 dev-browser-token。
  token: process.env.BROWSER_SERVICE_TOKEN || '',

  profilesDir: process.env.SHOPEE_PROFILE_DIR || path.resolve('profiles'),
  artifactsDir: process.env.ARTIFACTS_DIR || path.resolve('artifacts'),

  /**
   * ⚠️ 併發固定 1，而且是「全域」1，不是 per-profile。
   * 並行抓取是最明顯的機器人特徵之一；而且同一個 user-data-dir 本來就不能同時
   * 被兩個 browser 開啟（會把 profile 弄壞）。
   */
  concurrency: 1,

  timeouts: {
    // 蝦皮商品頁從 goto 到 get_pc 回來常要 20–40 秒
    xhr: num(process.env.XHR_TIMEOUT_MS, 45000),
    navigation: num(process.env.NAV_TIMEOUT_MS, 60000),
    task: num(process.env.TASK_TIMEOUT_MS, 180000),
  },

  // 動作之間的隨機停頓（秒），Laravel 會在 payload 裡覆寫
  jitter: {
    min: num(process.env.JITTER_MIN, 0.8),
    max: num(process.env.JITTER_MAX, 3.0),
  },

  shopee: {
    pdpApiPath: '/api/v4/pdp/get_pc',
    imageCdn: process.env.SHOPEE_IMAGE_CDN || 'https://down-tw.img.susercontent.com/file/',
    // ⚠️ 抓商品一律用這個「未登入」profile。登入 profile 只給之後的上架用，
    //    兩者絕不混用 —— 抓資料不需要帳號，拿帳號去抓等於白白承擔封號風險。
    anonymousProfile: 'shopee-anon',
    sellerProfile: process.env.SHOPEE_SELLER_PROFILE || 'shopee-seller',
  },
};

export default config;
