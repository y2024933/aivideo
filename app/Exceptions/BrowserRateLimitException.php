<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** 瀏覽器任務被頻率限制或執行時段擋下。message 一律是給 operator 看的繁中原因。 */
final class BrowserRateLimitException extends RuntimeException {}
