<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuildingCaseResource\RelationManagers;

use App\Jobs\PollKlingVideoJob;
use App\Services\Contracts\ImageGeneratorContract;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\ImageDownloader;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class ShotsRelationManager extends RelationManager
{
    protected static string $relationship = 'shots';

    protected static ?string $title = '分鏡腳本';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shot_id')->label('鏡頭 ID')->sortable(),
                Tables\Columns\TextColumn::make('shot_order')->label('順序')->sortable(),
                Tables\Columns\TextColumn::make('duration_seconds')->label('秒數'),
                Tables\Columns\TextColumn::make('scene_description')->label('場景描述')->limit(30),
                Tables\Columns\ImageColumn::make('image_url')->label('場景圖')
                    ->getStateUsing(fn ($record) => $record->image_url ? url($record->image_url) : null)
                    ->width(80)->height(80),
                Tables\Columns\TextColumn::make('image_status')->label('圖片狀態')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'info',
                        'done' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('video_status')->label('影片狀態')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'info',
                        'done' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subtitle')->label('字幕')->limit(30),
            ])
            ->defaultSort('shot_order')
            ->actions([
                Tables\Actions\ViewAction::make(),
                $this->regenerateSceneAction(),
                $this->regenerateVideoAction(),
            ]);
    }

    /**
     * 重跑單張場景圖
     */
    private function regenerateSceneAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('regenerate_scene')
            ->label('重跑場景圖')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn ($record) => in_array($record->image_status, ['done', 'failed', 'pending']))
            ->form([
                \Filament\Forms\Components\Select::make('model')
                    ->label('生成模型')
                    ->options([
                        'flux_kontext' => 'Flux Kontext（角色一致性高）',
                        'ideogram' => 'Ideogram（中文文字正確）',
                    ])
                    ->default('flux_kontext')
                    ->required(),
            ])
            ->action(function ($record, array $data) {
                $case = $this->getOwnerRecord();
                $approvedCharacter = $case->approvedCharacter;

                if (! $approvedCharacter?->image_url) {
                    Notification::make()->title('尚未核准角色或角色無圖片')->danger()->send();
                    return;
                }

                $imageGenerator = app(ImageGeneratorContract::class);
                $costPerImage = (float) config('services.fal.cost_per_image');
                $referenceUrl = $approvedCharacter->remote_url ?? $approvedCharacter->image_url;

                $modelMap = [
                    'flux_kontext' => \App\Services\FalKontextImageGenerator::MODEL_KONTEXT,
                    'ideogram' => \App\Services\FalKontextImageGenerator::MODEL_IDEOGRAM,
                ];
                $selectedModel = $modelMap[$data['model'] ?? 'flux_kontext'] ?? null;

                try {
                    $result = $imageGenerator->generateSceneImage(
                        $record->flux_prompt,
                        $referenceUrl,
                        $selectedModel
                    );

                    $localUrl = $result['image_url'] ? ImageDownloader::download($result['image_url'], 'scenes') : null;

                    $record->update([
                        'image_url' => $localUrl,
                        'image_request_id' => $result['request_id'],
                        'image_status' => $localUrl ? 'done' : 'failed',
                        'image_cost_usd' => $costPerImage,
                    ]);

                    $case->addCost($costPerImage);
                    if ($case->final_video_url) {
                        $case->update(['final_video_url' => null, 'render_id' => null]);
                    }
                    Notification::make()->title("鏡頭 {$record->shot_id} 場景圖重新生成完成")->success()->send();
                } catch (\Throwable $e) {
                    Log::error('[ShotsRelationManager::regenerateScene] 單張場景圖重跑失敗', [
                        'shot_id' => $record->shot_id,
                        'exception' => $e,
                    ]);

                    $record->update([
                        'image_status' => 'failed',
                        'image_retry_count' => $record->image_retry_count + 1,
                    ]);

                    Notification::make()->title('場景圖重新生成失敗')->danger()->send();
                }
            });
    }

    /**
     * 重跑單段動畫
     */
    private function regenerateVideoAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('regenerate_video')
            ->label('重跑動畫')
            ->icon('heroicon-o-play')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn ($record) => $record->image_url && in_array($record->video_status, ['done', 'failed']))
            ->action(function ($record) {
                if (! $record->image_url) {
                    Notification::make()->title('此鏡頭尚無場景圖')->danger()->send();
                    return;
                }

                $videoGenerator = app(VideoGeneratorContract::class);

                try {
                    $result = $videoGenerator->submitImageToVideo(
                        $record->image_url,
                        $record->kling_prompt ?? $record->flux_prompt,
                        (int) ($record->duration_seconds ?: config('services.kling.duration', 5)),
                    );

                    $record->update([
                        'video_request_id' => $result['task_id'],
                        'video_status' => 'processing',
                    ]);

                    PollKlingVideoJob::dispatch($record->id, $result['task_id'])->delay(now()->addSeconds(15));

                    $case = $record->buildingCase;
                    if ($case?->final_video_url) {
                        $case->update(['final_video_url' => null, 'render_id' => null]);
                    }

                    Notification::make()->title("鏡頭 {$record->shot_id} 動畫重新生成中")->body('背景處理中，請稍後刷新頁面')->success()->send();
                } catch (\Throwable $e) {
                    Log::error('[ShotsRelationManager::regenerateVideo] 單段動畫重跑失敗', [
                        'shot_id' => $record->shot_id,
                        'exception' => $e,
                    ]);

                    $record->update([
                        'video_status' => 'failed',
                        'video_error' => $e->getMessage(),
                    ]);

                    Notification::make()->title('動畫重新生成失敗')->danger()->send();
                }
            });
    }
}
