<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\NewsArticleResource;
use App\Models\NewsCategory;
use App\Models\Site;
use Filament\Actions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListNewsArticles extends ListRecords
{
    protected static string $resource = NewsArticleResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();
        $defaultSiteId = $isSuperAdmin ? null : $user?->sites()->value('sites.id');

        return [
            Actions\Action::make('manageCategories')
                ->label('管理分類')
                ->icon('heroicon-o-tag')
                ->color('gray')
                ->fillForm(function () use ($isSuperAdmin, $defaultSiteId) {
                    if ($isSuperAdmin) return [];

                    return [
                        'site_id' => $defaultSiteId,
                        'categories' => $defaultSiteId
                            ? NewsCategory::where('site_id', $defaultSiteId)
                                ->orderBy('sort_order')->get(['id', 'name', 'sort_order'])->toArray()
                            : [],
                    ];
                })
                ->form([
                    Select::make('site_id')
                        ->label('選擇網站')
                        ->options(fn () => Site::pluck('name', 'id'))
                        ->hidden(! $isSuperAdmin)
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, $state) {
                            $set('categories', NewsCategory::where('site_id', $state)
                                ->orderBy('sort_order')->get(['id', 'name', 'sort_order'])->toArray());
                        }),
                    Repeater::make('categories')
                        ->label('消息分類')
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')->label('名稱')->required(),
                            TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->visible(fn (callable $get) => filled($get('site_id')) || ! $isSuperAdmin),
                ])
                ->modalWidth('lg')
                ->stickyModalFooter()
                ->stickyModalHeader()
                ->extraModalWindowAttributes(['style' => 'max-height:80vh; overflow-y:auto;'])
                ->modalFooterActionsAlignment('center')
                ->action(function (array $data) use ($defaultSiteId) {
                    $siteId = $data['site_id'] ?? $defaultSiteId;
                    $user = auth()->user();

                    if (! $user->isSuperAdmin() && ! $user->sites()->where('sites.id', $siteId)->exists()) {
                        Notification::make()->danger()->title('無權限操作此站台')->send();
                        return;
                    }

                    $existingIds = [];

                    foreach ($data['categories'] as $item) {
                        if (! empty($item['id'])) {
                            NewsCategory::where('id', $item['id'])->where('site_id', $siteId)->update([
                                'name' => $item['name'],
                                'slug' => \Illuminate\Support\Str::slug($item['name']),
                                'sort_order' => $item['sort_order'] ?? 0,
                            ]);
                            $existingIds[] = $item['id'];
                        } else {
                            $new = NewsCategory::create([
                                'site_id' => $siteId,
                                'name' => $item['name'],
                                'slug' => \Illuminate\Support\Str::slug($item['name']),
                                'sort_order' => $item['sort_order'] ?? 0,
                            ]);
                            $existingIds[] = $new->id;
                        }
                    }

                    // 刪除不在列表中且沒有 newsArticles 引用的
                    NewsCategory::where('site_id', $siteId)
                        ->whereNotIn('id', $existingIds)
                        ->whereDoesntHave('newsArticles')
                        ->delete();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
