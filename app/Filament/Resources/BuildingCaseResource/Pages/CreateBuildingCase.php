<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuildingCaseResource\Pages;

use App\Filament\Resources\BuildingCaseResource;
use App\Services\MarkdownScriptParser;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

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

                $fillData = array_filter([
                    'name' => $result['name'] ?? '',
                    'builder_name' => $result['builder_name'] ?? '',
                    'character_nickname' => $result['character_nickname'] ?? '',
                    'character_dna' => $result['character_dna'] ?? '',
                    'location' => $result['location'] ?? null,
                    'area_range' => $result['area_range'] ?? null,
                    'target_audience' => $result['target_audience'] ?? null,
                    'tone' => $result['tone'] ?? null,
                    'video_length_seconds' => $result['video_length_seconds'] ?? null,
                    'shots' => $result['shots'] ?? [],
                ], fn ($v) => $v !== null);

                // 如果有 meta 資料，存入 script_v2
                if (! empty($result['meta'])) {
                    $fillData['script_v2'] = $result['meta'];
                }

                $this->form->fill($fillData);

                $shotCount = count($result['shots'] ?? []);
                Notification::make()
                    ->title('匯入成功')
                    ->body("已解析 {$shotCount} 個鏡頭")
                    ->success()
                    ->send();
            });
    }
}
