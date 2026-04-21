<?php

namespace App\Services;

use App\Models\LineChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessagingService
{
    public function pushMessage(LineChannel $channel, string $to, string $text): bool
    {
        try {
            $response = Http::withToken($channel->channel_access_token)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $to,
                    'messages' => [['type' => 'text', 'text' => $text]],
                ]);

            if ($response->failed()) {
                Log::warning('LINE push message 失敗', ['to' => $to, 'status' => $response->status(), 'body' => $response->body()]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('LINE push message 例外', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function verifySignature(string $body, string $signature, string $channelSecret): bool
    {
        return hash_equals(base64_encode(hash_hmac('sha256', $body, $channelSecret, true)), $signature);
    }

    public function getGroupSummary(LineChannel $channel, string $groupId): ?array
    {
        try {
            $response = Http::withToken($channel->channel_access_token)
                ->get("https://api.line.me/v2/bot/group/{$groupId}/summary");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getProfile(LineChannel $channel, string $userId): ?array
    {
        try {
            $response = Http::withToken($channel->channel_access_token)
                ->get("https://api.line.me/v2/bot/profile/{$userId}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
