<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * 這個實作刻意不支援該操作（而不是「失敗了」）。
 *
 * 用在可插拔 provider 上：例如純 Ken Burns 模式（VideoProvider::None）根本沒有
 * AI 動畫任務可送，呼叫端看到這個例外就該知道是「設定本來就不該走到這裡」，
 * 不是暫時性錯誤，重試也不會好。
 */
final class UnsupportedOperationException extends RuntimeException {}
