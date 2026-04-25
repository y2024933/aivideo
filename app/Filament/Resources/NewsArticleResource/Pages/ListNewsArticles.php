<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\NewsArticleResource;
use App\Models\Site;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListNewsArticles extends ListRecords
{
    protected static string $resource = NewsArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manageCategories')
                ->label('管理分類')
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
                        ->default(fn () => auth()->user()?->isSuperAdmin() ? null : auth()->user()?->sites()->value('sites.id'))
                        ->visible(fn () => auth()->user()?->isSuperAdmin())
                        ->dehydrated(true)
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, $state) {
                            $categories = \App\Models\NewsCategory::where('site_id', $state)
                                ->orderBy('sort_order')
                                ->get(['id', 'name', 'sort_order'])
                                ->toArray();
                            $set('categories', $categories);
                        }),
                    \Filament\Forms\Components\Repeater::make('categories')
                        ->label('消息分類')
                        ->schema([
                            \Filament\Forms\Components\Hidden::make('id'),
                            TextInput::make('name')->label('名稱')->required(),
                            TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                        ])
                        ->columns(2)
                        ->default(function () {
                            $user = auth()->user();
                            if ($user?->isSuperAdmin()) return [];
                            $siteId = $user?->sites()->value('sites.id');
                            if (! $siteId) return [];
                            return \App\Models\NewsCategory::where('site_id', $siteId)
                                ->orderBy('sort_order')->get(['id', 'name', 'sort_order'])->toArray();
                        })
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
                    $user = auth()->user();

                    if (! $user->isSuperAdmin() && ! $user->sites()->where('sites.id', $siteId)->exists()) {
                        \Filament\Notifications\Notification::make()->danger()->title('無權限操作此站台')->send();
                        return;
                    }

                    $existingIds = [];

                    foreach ($data['categories'] as $item) {
                        if (! empty($item['id'])) {
                            \App\Models\NewsCategory::where('id', $item['id'])->where('site_id', $siteId)->update([
                                'name' => $item['name'],
                                'slug' => \Illuminate\Support\Str::slug($item['name']),
                                'sort_order' => $item['sort_order'] ?? 0,
                            ]);
                            $existingIds[] = $item['id'];
                        } else {
                            $new = \App\Models\NewsCategory::create([
                                'site_id' => $siteId,
                                'name' => $item['name'],
                                'slug' => \Illuminate\Support\Str::slug($item['name']),
                                'sort_order' => $item['sort_order'] ?? 0,
                            ]);
                            $existingIds[] = $new->id;
                        }
                    }

                    // 刪除不在列表中且沒有 newsArticles 引用的
                    \App\Models\NewsCategory::where('site_id', $siteId)
                        ->whereNotIn('id', $existingIds)
                        ->whereDoesntHave('newsArticles')
                        ->delete();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
