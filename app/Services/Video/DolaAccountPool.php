<?php

declare(strict_types=1);

namespace App\Services\Video;

/**
 * Dola 帳號（瀏覽器 profile）池 —— 介面預留，**第一版沒有輪替邏輯**。
 *
 * ⚠️⚠️ 使用前必讀的風險（使用者已知悉並自行決定，但程式裡要留痕）：
 *
 *  1. Dola 用 Auth.js + Google OAuth 登入，**沒有 email／password 路徑**。
 *     也就是說沒有「填帳密自動登入」這條路，session 只能人工建立一次。
 *
 *  2. Google 對自動化瀏覽器的登入偵測極嚴，**絕對不能自動重登**
 *     （會直接鎖 Google 帳號，且只能人工申訴）。session 失效時唯一正確的動作是
 *     亮紅燈通知人工處理 —— 任何「偵測到登出就自動登入」的程式碼都不准寫進來。
 *
 *  3. **用多個 Google 帳號輪替刷免費額度是明確的 ToS 違規**，而且 Google 會把
 *     同 IP／同裝置登入過的帳號互相關聯，有連鎖封號風險（一個被抓，其他一起死）。
 *
 *  4. 真要用，Dola 帳號必須是**與主帳號完全無關的專用 Google 帳號**：
 *     不綁手機、不放任何個人資料、不要用它登入其他服務。
 *
 * 為什麼不開 dola_accounts 表：第一版沒有任何讀者會用到「每個帳號的額度／冷卻時間」，
 * 建一張沒有讀者的表等於先寫一個之後一定會改的 schema。profile 名稱直接從
 * config('services.browser.profiles.dola')（逗號分隔）讀。
 */
final class DolaAccountPool
{
    /**
     * 借一個 profile 來用。
     *
     * 第一版固定回 profiles[0] —— 刻意不輪替（見上面風險 3）。
     */
    public function acquire(): string
    {
        return $this->profiles()[0]
            ?? throw new \RuntimeException('沒有可用的 Dola 瀏覽器 profile，請設定 BROWSER_PROFILES_DOLA。');
    }

    /**
     * 歸還 profile。第一版是 no-op（沒有池子就沒有歸還）。
     *
     * $quotaExhausted 先留在簽章裡：真正實作時「免費額度用完」要走人工處理，
     * 不是自動換下一個帳號。
     */
    public function release(string $profile, bool $quotaExhausted = false): void
    {
        // no-op
    }

    /**
     * 池子現況，給 Filament widget 顯示用。
     *
     * @return array{profiles: list<string>, count: int, browser_ready: bool, implemented: bool}
     */
    public function status(): array
    {
        $profiles = $this->profiles();

        return [
            'profiles' => $profiles,
            'count' => count($profiles),
            'browser_ready' => filled(config('services.browser.base_url')),
            // 永遠是 false，直到真的把瀏覽器自動化寫完
            'implemented' => false,
        ];
    }

    /** @return list<string> */
    private function profiles(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('services.browser.profiles.dola')),
        ), 'strlen'));
    }
}
