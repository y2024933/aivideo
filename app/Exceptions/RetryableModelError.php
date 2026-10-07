<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * 可以換一個 model 重試的錯誤。
 *
 * 免費 tier 的實際狀況：熱門 model（gemini-3.8-flash）常回 503 high demand、
 * -latest 這類 alias 可能對特定帳號直接 hang 到逾時、舊版 model（2.5 系列）
 * 對新建立的專案回 404 已下架。這些換個 model 就能繼續，不該讓整個寫稿流程失敗。
 *
 * 反之 400（schema 錯）、安全攔截、key 無效換 model 也沒用，不歸這類。
 */
final class RetryableModelError extends \RuntimeException {}
