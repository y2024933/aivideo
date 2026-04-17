<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavigationItemResource\Pages;
use App\Models\NavigationItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NavigationItemResource extends Resource
{
    protected static ?string $model = NavigationItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?string $navigationLabel = '選單管理';

    protected static ?string $modelLabel = '選單項目';

    protected static ?string $pluralModelLabel = '選單項目';

    protected static ?string $navigationGroup = '網站內容';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('site_id')
                ->label('網站')
                ->relationship('site', 'name')
                ->required()
                ->reactive()
                ->visible(fn () => auth()->user()?->isSuperAdmin())
                ->default(fn () => auth()->user()?->sites()->value('sites.id')),
            Select::make('position')
                ->label('選單位置')
                ->options([
                    'primary' => '第一層 Header',
                    'secondary' => '第二層 Header',
                    'footer' => 'Footer',
                ])
                ->default('primary')
                ->required(),
            Select::make('parent_id')
                ->label('上層選單')
                ->options(function (callable $get): array {
                    $siteId = $get('site_id') ?: auth()->user()?->sites()->value('sites.id');

                    return NavigationItem::query()
                        ->where('site_id', $siteId)
                        ->whereNull('parent_id')
                        ->orderBy('sort_order')
                        ->pluck('label', 'id')
                        ->all();
                })
                ->searchable()
                ->preload(),
            Select::make('page_id')
                ->label('對應頁面')
                ->options(function (callable $get): array {
                    $siteId = $get('site_id') ?: auth()->user()?->sites()->value('sites.id');

                    return \App\Models\Page::query()
                        ->where('site_id', $siteId)
                        ->orderBy('sort_order')
                        ->pluck('title', 'id')
                        ->all();
                })
                ->searchable()
                ->preload(),
            TextInput::make('label')
                ->label('顯示名稱')
                ->required()
                ->maxLength(255),
            TextInput::make('url')
                ->label('自訂連結')
                ->helperText('若有選擇對應頁面，可留空。')
                ->maxLength(255),
            Select::make('target')
                ->label('開啟方式')
                ->options([
                    '_self' => '同視窗',
                    '_blank' => '新視窗',
                ])
                ->default('_self')
                ->required(),
            TextInput::make('sort_order')
                ->label('排序')
                ->numeric()
                ->default(0),
            Toggle::make('is_visible')
                ->label('顯示')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                TextColumn::make('site.name')
                    ->label('網站')
                    ->toggleable(),
                TextColumn::make('position')
                    ->label('位置')
                    ->badge(),
                TextColumn::make('label')
                    ->label('名稱')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.label')
                    ->label('上層選單')
                    ->placeholder('-'),
                TextColumn::make('page.title')
                    ->label('對應頁面')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable(),
                IconColumn::make('is_visible')
                    ->label('顯示')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('position')
                    ->label('位置')
                    ->options([
                        'primary' => '第一層 Header',
                        'secondary' => '第二層 Header',
                        'footer' => 'Footer',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNavigationItems::route('/'),
            'create' => Pages\CreateNavigationItem::route('/create'),
            'edit' => Pages\EditNavigationItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('site_id', $user->sites()->pluck('sites.id'));
    }
}
