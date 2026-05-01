<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuildingCaseResource\Pages;

use App\Enums\CaseStatus;
use App\Filament\Resources\BuildingCaseResource;
use App\Models\BuildingCase;
use App\Services\MarkdownScriptParser;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateBuildingCase extends CreateRecord
{
    protected static string $resource = BuildingCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importMarkdownAction(),
        ];
    }

    private function importMarkdownAction(): Action
    {
        return Action::make('import_markdown')
            ->label('匯入 MD 腳本')
            ->icon('heroicon-o-document-arrow-down')
            ->form([
                Textarea::make('markdown_content')
                    ->label('將 Cowork 產出的 MD 腳本貼在這裡')
                    ->rows(15)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $parser = new MarkdownScriptParser();
                $result = $parser->parse($data['markdown_content']);

                $case = DB::transaction(function () use ($result) {
                    $case = BuildingCase::create([
                        'name' => $result['name'] ?? '',
                        'builder_name' => $result['builder_name'] ?? '',
                        'character_nickname' => $result['character_nickname'] ?? '',
                        'character_dna' => $result['character_dna'] ?? '',
                        'location' => $result['location'] ?? null,
                        'area_range' => $result['area_range'] ?? null,
                        'target_audience' => $result['target_audience'] ?? null,
                        'tone' => $result['tone'] ?? null,
                        'video_length_seconds' => $result['video_length_seconds'] ?? 60,
                        'voiceover_full' => $result['voiceover_full'] ?? null,
                        'voice_id_preferred' => $result['voice_id_preferred'] ?? null,
                        'compliance_watermark' => $result['compliance_watermark'] ?? null,
                        'compliance_footer' => $result['compliance_footer'] ?? null,
                        'bgm_keywords' => $result['bgm_keywords'] ?? null,
                        'review_passed' => $result['review_passed'] ?? false,
                        'review_v2_score' => $result['review_v2_score'] ?? null,
                        'review_meta' => $result['review_meta'] ?? null,
                        'script_v2' => $result['meta'] ?? null,
                        'status' => CaseStatus::Draft,
                    ]);

                    $case->shots()->createMany(
                        collect($result['shots'] ?? [])->map(fn (array $shot) => [
                            'shot_id' => $shot['shot_id'],
                            'shot_order' => $shot['shot_order'],
                            'duration_seconds' => $shot['duration_seconds'] ?? 5,
                            'use_model' => $shot['use_model'] ?? null,
                            'flux_prompt' => $shot['flux_prompt'] ?? '',
                            'kling_prompt' => $shot['kling_prompt'] ?? '',
                            'voiceover_text' => $shot['voiceover_text'] ?? '',
                            'subtitle' => $shot['subtitle'] ?? '',
                            'emotion' => $shot['emotion'] ?? '',
                        ])->all()
                    );

                    return $case;
                });

                Notification::make()
                    ->title('匯入成功')
                    ->body("已建立建案「{$case->name}」，含 {$case->shots()->count()} 個鏡頭")
                    ->success()
                    ->send();

                $this->redirect(BuildingCaseResource::getUrl('edit', ['record' => $case]));
            });
    }
}
