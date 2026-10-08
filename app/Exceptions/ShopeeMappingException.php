<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** get_pc 回應連標題都映射不出來（蝦皮改版或回了錯誤 payload）。 */
final class ShopeeMappingException extends RuntimeException {}
