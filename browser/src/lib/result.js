/**
 * 與 PHP 端 App\Data\Browser\BrowserResult 一對一的回傳格式。
 * 欄位名改這裡就要同步改 PHP，否則會靜默變成 status=failed。
 */

export const STATUS = {
  succeeded: 'succeeded',
  degraded: 'degraded',
  failed: 'failed',
  needsManual: 'needs_manual',
};

const base = (status, extra = {}) => ({
  status,
  strategy: null,
  data: {},
  errorCode: null,
  errorMessage: null,
  durationMs: 0,
  artifacts: {},
  ...extra,
});

export const ok = (data, strategy = 'xhr', durationMs = 0, artifacts = {}) =>
  base(STATUS.succeeded, { strategy, data, durationMs, artifacts });

/** 降級：拿到資料但來源不可靠，PHP 端不會自動放行 checkpoint ① */
export const degraded = (data, errorCode, errorMessage, durationMs = 0, artifacts = {}) =>
  base(STATUS.degraded, { strategy: 'dom', data, errorCode, errorMessage, durationMs, artifacts });

export const failed = (errorCode, errorMessage, durationMs = 0, artifacts = {}) =>
  base(STATUS.failed, { errorCode, errorMessage: String(errorMessage ?? ''), durationMs, artifacts });

/** 人機驗證、登入過期 —— 重跑幾次都一樣，一定要人介入 */
export const needsManual = (errorCode, errorMessage, durationMs = 0, artifacts = {}) =>
  base(STATUS.needsManual, { errorCode, errorMessage: String(errorMessage ?? ''), durationMs, artifacts });

/** 業務錯誤（非基礎設施錯誤）一律用 HTTP 200 回，讓 PHP 端不要重試 */
export const isBusinessStatus = (status) =>
  [STATUS.succeeded, STATUS.degraded, STATUS.failed, STATUS.needsManual].includes(status);
