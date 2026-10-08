/**
 * 瀏覽器自動化服務（Fastify）。
 *
 * 設計重點：
 *   1. 全域 p-queue(1) —— 所有任務序列化。併發抓取是最明顯的機器人特徵，
 *      而且 Chromium 一個 instance 就吃掉 1GB 以上，容器只有 3GB。
 *   2. Bearer auth —— 這個服務能操作已登入的蝦皮帳號，不可以裸奔。
 *   3. 統一回傳格式 —— 與 PHP 的 App\Data\Browser\BrowserResult 一對一。
 *   4. 業務錯誤回 HTTP 200（body.status = failed/needs_manual），
 *      基礎設施錯誤才回 5xx。PHP 端只對 5xx 與連線失敗重試。
 */
import { createReadStream } from 'node:fs';
import fs from 'node:fs/promises';
import Fastify from 'fastify';
import PQueue from 'p-queue';
import config from './config.js';
import { closeAll } from './browser/pool.js';
import { resolveArtifact } from './browser/artifacts.js';
import { withTimeout } from './lib/retry.js';
import { failed } from './lib/result.js';
import { shopeeProduct } from './tasks/shopeeProduct.js';
import { externalImages } from './tasks/externalImages.js';
import { shopeeSession } from './tasks/shopeeSession.js';

const app = Fastify({
  logger: { level: process.env.LOG_LEVEL || 'info' },
  // 瀏覽器任務很慢，Fastify 預設 requestTimeout 會先砍掉連線
  requestTimeout: 0,
  bodyLimit: 1_048_576,
});

/** ⚠️ 全域併發 1。不要改成 CPU 數量。 */
const queue = new PQueue({ concurrency: config.concurrency });

app.addHook('onRequest', async (request, reply) => {
  if (request.url === '/health' || !config.token) {
    return;
  }

  const header = request.headers.authorization || '';

  if (header !== `Bearer ${config.token}`) {
    reply.code(401);

    throw new Error('unauthorized');
  }
});

app.setErrorHandler((error, request, reply) => {
  app.log.error({ err: error, url: request.url }, 'request failed');

  const code = reply.statusCode >= 400 ? reply.statusCode : 500;

  reply.code(code).send({
    ...failed(code === 401 ? 'unauthorized' : 'internal_error', error?.message || String(error)),
  });
});

app.get('/health', async () => ({
  status: 'ok',
  queued: queue.size,
  running: queue.pending,
  concurrency: config.concurrency,
}));

/** 下載失敗時留下的截圖／trace。⚠️ 路徑必須經過 resolveArtifact 擋 path traversal。 */
app.get('/artifacts/*', async (request, reply) => {
  const target = resolveArtifact(request.params['*']);

  if (!target) {
    return reply.code(400).send(failed('invalid_path', 'artifact 路徑不合法'));
  }

  try {
    await fs.access(target);
  } catch {
    return reply.code(404).send(failed('not_found', 'artifact 不存在（容器可能已重建）'));
  }

  return reply.type('application/octet-stream').send(createReadStream(target));
});

/**
 * 把任務包成「排進全域佇列 + 整體逾時 + 永遠回 200 的業務結果」。
 */
function register(path, handler) {
  app.post(path, async (request, reply) => {
    const payload = request.body ?? {};

    try {
      const result = await queue.add(() =>
        withTimeout(handler(payload), config.timeouts.task, `任務逾時（${config.timeouts.task}ms）`),
      );

      return reply.code(200).send(result);
    } catch (error) {
      // 走到這裡代表連「任務自己的錯誤處理」都失效（逾時、OOM），
      // 仍然回 200 + status=failed：這是業務失敗，重試幫不上忙。
      app.log.error({ err: error, path }, 'task crashed');

      return reply.code(200).send(failed('task_crashed', error?.message || String(error)));
    }
  });
}

register('/tasks/shopee-product', shopeeProduct);
register('/tasks/external-images', externalImages);
register('/tasks/shopee-session', shopeeSession);

/** 收到停止訊號時一定要關掉 context，否則 persistent profile 可能留下 SingletonLock */
async function shutdown(signal) {
  app.log.info({ signal }, 'shutting down');

  try {
    await queue.onIdle();
    await closeAll();
    await app.close();
  } finally {
    process.exit(0);
  }
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

try {
  await app.listen({ port: config.port, host: config.host });
} catch (error) {
  app.log.error(error);
  process.exit(1);
}

export default app;
