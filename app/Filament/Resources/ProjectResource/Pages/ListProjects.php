<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Site;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manageStatuses')
                ->label('管理作品類型')
                ->icon('heroicon-o-tag')
                ->color('gray')
                ->form([
                    Select::make('site_id')
                        ->label('選擇網站')
                        ->options(function () {
                            $user = auth()->user();
                            return $user?->isSuperAdmin()
                                ? Site::pluck('name', 'id')
                                : $user?->sites()->pluck('name', 'sites.id') ?? [];
                        })
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, $state) {
                            $statuses = \App\Models\ProjectStatus::where('site_id', $state)
                                ->orderBy('sort_order')
                                ->get(['id', 'name', 'sort_order'])
                                ->toArray();
                            $set('statuses', $statuses);
                        }),
                    \Filament\Forms\Components\Repeater::make('statuses')
                        ->label('作品類型')
                        ->schema([
                            \Filament\Forms\Components\Hidden::make('id'),
                            TextInput::make('name')->label('名稱')->required(),
                            TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                        ])
                        ->columns(2)
                        ->default([])
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->visible(fn (callable $get) => filled($get('site_id'))),
                ])
                ->modalWidth('lg')
                ->stickyModalFooter()
                ->stickyModalHeader()
                ->extraModalWindowAttributes(['style' => 'max-height:80vh; overflow-y:auto;'])
                ->modalFooterActionsAlignment('center')
                ->action(function (array $data) {
                    $siteId = $data['site_id'];
                    $existingIds = [];

                    foreach ($data['statuses'] as $item) {
                        if (! empty($item['id'])) {
                            \App\Models\ProjectStatus::where('id', $item['id'])->update([
                                'name' => $item['name'],
                                'slug' => \Illuminate\Support\Str::slug($item['name']),
                                'sort_order' => $item['sort_order'] ?? 0,
                            ]);
                            $existingIds[] = $item['id'];
                        } else {
                            $new = \App\Models\ProjectStatus::create([
                                'site_id' => $siteId,
                                'name' => $item['name'],
                                'slug' => \Illuminate\Support\Str::slug($item['name']),
                                'sort_order' => $item['sort_order'] ?? 0,
                            ]);
                            $existingIds[] = $new->id;
                        }
                    }

                    // 刪除不在列表中的（沒有建案引用的才刪）
                    \App\Models\ProjectStatus::where('site_id', $siteId)
                        ->whereNotIn('id', $existingIds)
                        ->whereDoesntHave('projects')
                        ->delete();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
