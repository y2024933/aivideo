<?php

namespace App\Http\Controllers;

use App\Models\LineChannel;
use App\Models\LineTarget;
use App\Services\LineMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineWebhookController extends Controller
{
    public function handle(Request $request, int $channel, LineMessagingService $lineService): JsonResponse
    {
        $lineChannel = LineChannel::find($channel);
        if (! $lineChannel?->is_active) {
            return response()->json(['message' => 'ok']);
        }

        $signature = $request->header('X-Line-Signature', '');
        if (! $lineService->verifySignature($request->getContent(), $signature, $lineChannel->channel_secret)) {
            return response()->json(['message' => 'invalid signature'], 403);
        }

        $events = $request->input('events', []);
        foreach ($events as $event) {
            match ($event['type'] ?? null) {
                'join' => $this->handleJoin($lineChannel, $event, $lineService),
                'follow' => $this->handleFollow($lineChannel, $event, $lineService),
                'leave' => $this->handleLeave($lineChannel, $event),
                'unfollow' => $this->handleUnfollow($lineChannel, $event),
                default => null,
            };
        }

        return response()->json(['message' => 'ok']);
    }

    private function handleJoin(LineChannel $channel, array $event, LineMessagingService $lineService): void
    {
        $groupId = $event['source']['groupId'] ?? null;
        if (! $groupId) return;

        $summary = $lineService->getGroupSummary($channel, $groupId);

        LineTarget::updateOrCreate(
            ['line_channel_id' => $channel->id, 'line_id' => $groupId],
            ['type' => 'group', 'display_name' => $summary['groupName'] ?? '未知群組', 'is_active' => true],
        );
    }

    private function handleFollow(LineChannel $channel, array $event, LineMessagingService $lineService): void
    {
        $userId = $event['source']['userId'] ?? null;
        if (! $userId) return;

        $profile = $lineService->getProfile($channel, $userId);

        LineTarget::updateOrCreate(
            ['line_channel_id' => $channel->id, 'line_id' => $userId],
            ['type' => 'user', 'display_name' => $profile['displayName'] ?? '未知使用者', 'is_active' => true],
        );
    }

    private function handleLeave(LineChannel $channel, array $event): void
    {
        $groupId = $event['source']['groupId'] ?? null;
        if ($groupId) {
            LineTarget::where('line_channel_id', $channel->id)->where('line_id', $groupId)->update(['is_active' => false]);
        }
    }

    private function handleUnfollow(LineChannel $channel, array $event): void
    {
        $userId = $event['source']['userId'] ?? null;
        if ($userId) {
            LineTarget::where('line_channel_id', $channel->id)->where('line_id', $userId)->update(['is_active' => false]);
        }
    }
}
