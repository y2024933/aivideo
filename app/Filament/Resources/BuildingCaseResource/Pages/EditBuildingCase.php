<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuildingCaseResource\Pages;

use App\Enums\CaseStatus;
use App\Filament\Resources\BuildingCaseResource;
use App\Jobs\GenerateCharacterPreviewJob;
use App\Jobs\PollKlingVideoJob;
use App\Jobs\PollRemotionRenderJob;
use App\Services\Contracts\ImageGeneratorContract;
use App\Services\Contracts\TtsContract;
use App\Services\Contracts\VideoEditorContract;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\ImageDownloader;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditBuildingCase extends EditRecord
{
    protected static string $resource = BuildingCaseResource::class;

    public function getHeading(): string
    {
        $status = $this->record->status;

        return "編輯建案：{$this->record->name}（{$status->label()}）";
    }


    protected function getHeaderActions(): array
    {
        return [
            $this->importMarkdownAction(),
            $this->downloadAssetsAction(),
            Actions\DeleteAction::make(),
        ];
    }

    private function importMarkdownAction(): Action
    {
        return Action::make('import_markdown')
            ->label('匯入 MD 腳本')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->form([
                Forms\Components\Textarea::make('markdown_content')
                    ->label('將 Cowork 產出的 MD 腳本貼在這裡（會覆蓋現有資料）')
                    ->rows(15)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $parser = new \App\Services\MarkdownScriptParser();
                $result = $parser->parse($data['markdown_content']);

                // 更新建案基本資料
                $fillData = array_filter([
                    'name' => $result['name'] ?? null,
                    'builder_name' => $result['builder_name'] ?? null,
                    'character_nickname' => $result['character_nickname'] ?? null,
                    'character_dna' => $result['character_dna'] ?? null,
                    'location' => $result['location'] ?? null,
                    'area_range' => $result['area_range'] ?? null,
                    'target_audience' => $result['target_audience'] ?? null,
                    'tone' => $result['tone'] ?? null,
                    'video_length_seconds' => $result['video_length_seconds'] ?? null,
                ]);
                if ($fillData) {
                    $this->record->update($fillData);
                }

                // 如果有 meta 資料，存入 script_v2
                if (! empty($result['meta'])) {
                    $this->record->update(['script_v2' => $result['meta']]);
                }

                // 刪除舊 shots，建立新 shots
                if (! empty($result['shots'])) {
                    $this->record->shots()->forceDelete();
                    foreach ($result['shots'] as $shot) {
                        $shot['image_status'] = 'pending';
                        $shot['video_status'] = 'pending';
                        $this->record->shots()->create($shot);
                    }
                }

                $shotCount = count($result['shots'] ?? []);
                Notification::make()
                    ->title('匯入成功')
                    ->body("已更新建案資料並重建 {$shotCount} 個鏡頭")
                    ->success()
                    ->send();

                $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
            });
    }

    private function downloadAssetsAction(): Action
    {
        return Action::make('download_assets')
            ->label('下載素材包')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->url(fn () => route('building-cases.download-assets', $this->record))
            ->openUrlInNewTab()
            ->visible(fn () => $this->record->shots()
                ->where(fn ($q) => $q->where('image_status', 'done')->orWhere('video_status', 'done'))
                ->exists());
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->generateCharactersAction(),
            $this->approveCharacterAction(),
            $this->generateScenesAction(),
            $this->approveImagesAction(),
            $this->generateVoiceoverAction(),
            $this->renderVideoAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * Action 1: 生成角色預覽
     */
    private function generateCharactersAction(): Action
    {
        return Action::make('generate_characters')
            ->label('生成角色預覽')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->visible(fn () => in_array($this->record->status, [CaseStatus::Draft, CaseStatus::CharacterFailed, CaseStatus::CharacterGenerating]))
            ->form([
                Forms\Components\TextInput::make('count')
                    ->label('生成數量')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(8)
                    ->required(),
            ])
            ->action(function (array $data) {
                $case = $this->record;
                $case->transitionTo(CaseStatus::CharacterGenerating, 'operator');
                $prompt = $case->character_dna ?? $case->name;
                $count = (int) ($data['count'] ?? 4);

                for ($i = 0; $i < $count; $i++) {
                    $option = $case->characterOptions()->create([
                        'prompt' => $prompt,
                        'status' => 'pending',
                    ]);
                    GenerateCharacterPreviewJob::dispatch($option->id, $case->id, $prompt);
                }

                Notification::make()->title('角色預覽生成中')->body("正在生成 {$count} 張，請稍後刷新頁面")->success()->send();
                $this->refreshFormData(['status']);
            });
    }

    /**
     * Action 2: 核准角色
     */
    private function approveCharacterAction(): Action
    {
        return Action::make('approve_character')
            ->label('核准角色')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn () => $this->record->status === CaseStatus::CharacterPendingReview)
            ->form([
                Select::make('character_option_id')
                    ->label('選擇角色')
                    ->options(function () {
                        $options = $this->record->characterOptions()
                            ->where('status', 'done')
                            ->orderBy('created_at')
                            ->get();
                        $result = [];
                        foreach ($options as $i => $opt) {
                            $num = $i + 1;
                            $time = $opt->created_at->format('H:i');
                            $result[$opt->id] = "角色 #{$num}（{$time} 生成）";
                        }
                        return $result;
                    })
                    ->required(),
            ])
            ->action(function (array $data) {
                $option = $this->record->characterOptions()->find($data['character_option_id']);
                if (! $option) {
                    return;
                }

                $this->record->update(['approved_character_id' => $option->id]);
                $this->record->transitionTo(CaseStatus::CharacterApproved, 'operator');

                Notification::make()->title('角色已核准')->success()->send();
                $this->refreshFormData(['status']);
            });
    }

    /**
     * Action 3: 生成場景圖（全部）
     */
    private function generateScenesAction(): Action
    {
        return Action::make('generate_scenes')
            ->label('生成場景圖')
            ->icon('heroicon-o-photo')
            ->color('primary')
            ->visible(fn () => in_array($this->record->status, [CaseStatus::CharacterApproved, CaseStatus::ScriptPendingReview, CaseStatus::ImagesPartial]))
            ->form([
                Select::make('model')
                    ->label('生成模型')
                    ->options([
                        'flux_kontext' => 'Flux Kontext（角色一致性高）',
                        'ideogram' => 'Ideogram（中文文字正確）',
                    ])
                    ->default('flux_kontext')
                    ->required(),
            ])
            ->action(function (array $data) {
                $case = $this->record;
                $approvedCharacter = $case->approvedCharacter;

                // remote_url 24 小時內有效，過期用本地圖
                $remoteStillValid = $approvedCharacter?->remote_url
                    && $approvedCharacter->updated_at?->gt(now()->subHours(24));
                $referenceUrl = $remoteStillValid
                    ? $approvedCharacter->remote_url
                    : ($approvedCharacter?->image_url ?? null);
                if (! $referenceUrl) {
                    Notification::make()->title('尚未核准角色或角色無圖片')->danger()->send();
                    return;
                }

                $case->transitionTo(CaseStatus::ImagesGenerating, 'operator');

                $imageGenerator = app(ImageGeneratorContract::class);
                $shots = $case->shots()->whereIn('image_status', ['pending', 'failed'])->whereNotNull('flux_prompt')->where('flux_prompt', '!=', '')->get();
                $costPerImage = (float) config('services.fal.cost_per_image');
                $totalCost = 0.0;
                $hasFailure = false;

                $modelMap = [
                    'flux_kontext' => \App\Services\FalKontextImageGenerator::MODEL_KONTEXT,
                    'ideogram' => \App\Services\FalKontextImageGenerator::MODEL_IDEOGRAM,
                ];
                $selectedModel = $modelMap[$data['model'] ?? 'flux_kontext'] ?? null;

                $useModelMap = [
                    'ideogram_v2_turbo' => \App\Services\FalKontextImageGenerator::MODEL_IDEOGRAM,
                    'flux_kontext' => \App\Services\FalKontextImageGenerator::MODEL_KONTEXT,
                    'flux_pro' => \App\Services\FalKontextImageGenerator::MODEL_FLUX_PRO,
                ];

                $characterDna = $case->character_dna ?? '';

                foreach ($shots as $shot) {
                    try {
                        // shot 層級的 use_model 優先於 UI 選擇
                        $shotModel = ($shot->use_model && isset($useModelMap[$shot->use_model]))
                            ? $useModelMap[$shot->use_model]
                            : $selectedModel;

                        // 只替換簡單的 [XXX DNA] 或 [XXX DNA v2] 佔位符（不含冒號的）
                        $prompt = $shot->flux_prompt;
                        if ($characterDna && preg_match('/\[[^\]]*DNA[^\]:]*\]/u', $prompt)) {
                            $prompt = preg_replace('/\[[^\]]*DNA[^\]:]*\]/u', $characterDna, $prompt);
                        }

                        $result = $imageGenerator->generateSceneImage(
                            $prompt,
                            $referenceUrl,
                            $shotModel
                        );

                        $localUrl = $result['image_url'] ? ImageDownloader::download($result['image_url'], 'scenes') : null;

                        $shot->update([
                            'image_url' => $localUrl,
                            'image_request_id' => $result['request_id'],
                            'image_status' => $localUrl ? 'done' : 'failed',
                            'image_cost_usd' => $costPerImage,
                        ]);

                        $totalCost += $costPerImage;
                    } catch (\Throwable $e) {
                        Log::error('[EditBuildingCase::generateScenes] 場景圖生成失敗', [
                            'shot_id' => $shot->shot_id,
                            'exception' => $e,
                        ]);

                        $shot->update([
                            'image_status' => 'failed',
                            'image_retry_count' => $shot->image_retry_count + 1,
                        ]);
                        $hasFailure = true;
                    }
                }

                if ($totalCost > 0) {
                    $case->addCost($totalCost);
                }

                // 檢查是否還有 pending 的 shot（包括沒有 prompt 的）
                $remainingPending = $case->shots()->whereIn('image_status', ['pending', 'failed'])->count();
                $newStatus = ($hasFailure || $remainingPending > 0) ? CaseStatus::ImagesPartial : CaseStatus::ImagesPendingReview;
                $case->transitionTo($newStatus, 'system');

                $message = $hasFailure ? '部分場景圖生成失敗，請檢查後重試' : '場景圖全部生成完成';
                Notification::make()->title($message)->{$hasFailure ? 'warning' : 'success'}()->send();
                $this->refreshFormData(['status']);
            });
    }

    /**
     * Action 4: 核准場景圖 + 開始動畫
     */
    private function approveImagesAction(): Action
    {
        return Action::make('approve_images')
            ->label('核准場景圖，開始動畫')
            ->icon('heroicon-o-play')
            ->color('success')
            ->visible(fn () => $this->record->status === CaseStatus::ImagesPendingReview)
            ->requiresConfirmation()
            ->action(function () {
                $case = $this->record;
                $case->transitionTo(CaseStatus::ImagesApproved, 'operator');

                $videoGenerator = app(VideoGeneratorContract::class);
                $shots = $case->shots()->where('image_status', 'done')->get();

                foreach ($shots as $shot) {
                    try {
                        $result = $videoGenerator->submitImageToVideo(
                            $shot->image_url,
                            $shot->kling_prompt ?? $shot->flux_prompt,
                            (int) ($shot->duration_seconds ?: config('services.kling.duration', 5)),
                        );

                        $shot->update([
                            'video_request_id' => $result['task_id'],
                            'video_status' => 'processing',
                        ]);

                        PollKlingVideoJob::dispatch($shot->id, $result['task_id'])->delay(now()->addSeconds(15));
                    } catch (\Throwable $e) {
                        Log::error('[EditBuildingCase::approveImages] 影片提交失敗', [
                            'shot_id' => $shot->shot_id,
                            'exception' => $e,
                        ]);

                        $shot->update([
                            'video_status' => 'failed',
                            'video_error' => $e->getMessage(),
                        ]);
                    }
                }

                $case->transitionTo(CaseStatus::ProducingFinal, 'system');

                Notification::make()->title('動畫生成已啟動')->body('背景處理中，請稍後刷新頁面')->success()->send();
                $this->refreshFormData(['status']);
            });
    }

    /**
     * Action 5: 生成配音
     */
    private function generateVoiceoverAction(): Action
    {
        return Action::make('generate_voiceover')
            ->label('生成配音')
            ->icon('heroicon-o-microphone')
            ->color('primary')
            ->visible(fn () => $this->record->shots()->count() > 0)
            ->form([
                Select::make('voice_name')
                    ->label('選擇聲音')
                    ->options([
                        'zh-TW-HsiaoChenNeural' => '曉臻（女，溫暖）',
                        'zh-TW-HsiaoYuNeural' => '曉雨（女，清亮）',
                        'zh-TW-YunJheNeural' => '雲哲（男）',
                    ])
                    ->default(fn () => $this->record->script_v2['voice_id_preferred'] ?? 'zh-TW-HsiaoChenNeural')
                    ->required(),
            ])
            ->action(function (array $data) {
                $case = $this->record;
                $shots = $case->shots()->orderBy('shot_order')->get();
                $voiceableShotCount = $shots->filter(fn ($s) => filled($s->voiceover_text))->count();

                if ($voiceableShotCount === 0) {
                    Notification::make()->title('此建案無配音稿文字')->danger()->send();
                    return;
                }

                $tts = app(TtsContract::class);
                $voiceName = $data['voice_name'];
                $doneCount = 0;
                $failCount = 0;

                foreach ($shots as $shot) {
                    if (blank($shot->voiceover_text)) {
                        continue;
                    }

                    try {
                        $result = $tts->synthesize($shot->voiceover_text, $voiceName);
                        $shot->update([
                            'voiceover_url' => $result['audio_url'],
                            'voiceover_remote_url' => $result['remote_url'] ?? null,
                            'voiceover_status' => 'done',
                            'voiceover_voice_id' => $voiceName,
                        ]);
                        $doneCount++;
                    } catch (\Throwable $e) {
                        Log::error('[EditBuildingCase::generateVoiceover] 配音生成失敗', [
                            'shot_id' => $shot->shot_id,
                            'exception' => $e,
                        ]);
                        $shot->update(['voiceover_status' => 'failed', 'voiceover_url' => null, 'voiceover_remote_url' => null]);
                        $failCount++;
                    }
                }

                // 配音重新生成後，清掉舊的成品影片讓使用者可以重新渲染
                if ($case->final_video_url) {
                    $case->update(['final_video_url' => null, 'render_id' => null]);
                }

                $message = $failCount > 0
                    ? "完成 {$doneCount} 段，失敗 {$failCount} 段"
                    : "全部 {$doneCount} 段配音生成完成";
                Notification::make()->title($message)->{$failCount > 0 ? 'warning' : 'success'}()->send();
                $this->refreshFormData(['status']);
            });
    }

    /**
     * Action 6: 渲染最終影片
     */
    private function renderVideoAction(): Action
    {
        return Action::make('render_video')
            ->label('渲染最終影片')
            ->icon('heroicon-o-film')
            ->color('warning')
            ->visible(function () {
                return $this->record->shots()->where('video_status', 'done')->exists()
                    && ! $this->record->render_id;
            })
            ->requiresConfirmation()
            ->action(function () {
                $case = $this->record;
                $videoEditor = app(VideoEditorContract::class);

                try {
                    $result = $videoEditor->submitRender($case);
                } catch (\Throwable $e) {
                    Log::error('[EditBuildingCase::renderVideo] 提交渲染失敗', ['exception' => $e]);
                    Notification::make()
                        ->title('影片渲染提交失敗')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                    return;
                }

                $case->update(['render_id' => $result['render_id']]);

                PollRemotionRenderJob::dispatch($case->id, $result['render_id'])
                    ->delay(now()->addSeconds(30));

                Notification::make()->title('影片渲染已提交')->body('請稍後刷新頁面查看進度')->success()->send();
                $this->refreshFormData(['status']);
            });
    }
}
