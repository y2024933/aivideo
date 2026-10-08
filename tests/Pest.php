<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Unit/Services', 'Unit/Compliance', 'Unit/Models', 'Unit/Llm', 'Unit/Video', 'Unit/Browser');

// Unit/Remotion 需要 config()（讀 config/video.php 與 remotion/ 原始碼比對），但不碰 DB。
// Unit/Config 只讀 config 陣列（Horizon supervisor 與 queue retry_after 的一致性），同樣不碰 DB。
//
// ⚠️ Unit/Browser 原本在這一組（只碰 cache），P9 的 BrowserHealthTest 需要查
//    browser_tasks 與 products，所以整組搬到上面的 RefreshDatabase 群組。
//    不要搬回來 —— 沒有 RefreshDatabase 時 DB 斷言會讀到上一個測試的殘留資料。
uses(Tests\TestCase::class)->in('Unit/Shopee', 'Unit/Remotion', 'Unit/Config');

// 「強制用 stub、不打真實 API」已移到 Tests\TestCase::setUp()。
// 原因：Pest 的 beforeEach 以宣告它的檔案為作用域，寫在 Pest.php 的 hook 不會套用到任何測試。

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
 * 全域攔截未被 fake 的對外請求。
 *
 * NoRealApiGuardTest 的字串掃描只看原始碼長相（「檔案裡有出現 Http::fake 就放行」），
 * 粒度太粗而且 regex 改壞時會靜默永遠通過。這一行攔的是實際封包，強得多：
 * 任何沒被 Http::fake 覆蓋的 outbound 請求都會當場丟例外，而不是真的送出去。
 *
 * 對這個專案特別重要 —— 打付費 API 最多花錢，打蝦皮會封帳號，
 * 而帳號是人工 SMS OTP 登入的，無法程式化恢復。
 */
uses()->beforeEach(function () {
    Illuminate\Support\Facades\Http::preventStrayRequests();
})->in('Feature', 'Unit');
