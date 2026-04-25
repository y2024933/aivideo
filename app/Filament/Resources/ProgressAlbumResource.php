<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgressAlbumResource\Pages;
use App\Models\ProgressAlbum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProgressAlbumResource extends Resource
{
    protected static ?string $model = ProgressAlbum::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationLabel = '工程相簿';

    protected static ?string $modelLabel = '工程相簿';

    protected static ?string $pluralModelLabel = '工程相簿';

    protected static ?string $navigationGroup = '網站內容';

    protected static ?int $navigationSort = 5; // 工程相簿

    public static function form(Form $form): Form
    {
        return $form->schema([
            \Filament\Forms\Components\Grid::make(2)->schema([
                Select::make('site_id')
                    ->label('網站')
                    ->relationship('site', 'name')
                    ->required()
                    ->hidden(fn () => ! auth()->user()?->isSuperAdmin())
                    ->default(fn () => auth()->user()?->sites()->value('sites.id'))
                    ->dehydrated(true),
                Toggle::make('is_published')->label('上架')->default(true)->inline(false),
            ]),
            Grid::make(3)->schema([
                Select::make('project_id')
                    ->label('建案名稱')
                    ->relationship('project', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                DatePicker::make('reported_at')
                    ->label('施工日期')
                    ->required()
                    ->default(now()),
                TextInput::make('progress_percent')
                    ->label('工程進度 %')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->suffix('%'),
            ]),
            FileUpload::make('gallery')
                ->label('施工照片')
                ->disk('public')
                ->directory('progress-albums')
                ->image()
                ->multiple()
                ->reorderable()
                ->imageEditor()
                ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048))
                ->panelLayout('grid')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reported_at', 'desc')
            ->columns([
                TextColumn::make('site.name')->label('網站')->searchable(auth()->user()?->isSuperAdmin() ?? false)->visible(fn () => auth()->user()?->isSuperAdmin())->toggleable(),
                TextColumn::make('project.name')
                    ->label('建案')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reported_at')
                    ->label('施工日期')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('progress_percent')
                    ->label('進度')
                    ->suffix('%')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('gallery')
                    ->label('照片數')
                    ->getStateUsing(fn ($record) => count($record->gallery ?? []) . ' 張'),
                IconColumn::make('is_published')->label('上架')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('site_id')
                    ->label('網站')
                    ->relationship('site', 'name')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
                SelectFilter::make('project_id')
                    ->label('建案')
                    ->options(function () {
                        $user = auth()->user();
                        $query = \App\Models\Project::query();
                        if (! $user?->isSuperAdmin()) {
                            $query->whereIn('site_id', $user?->sites()->pluck('sites.id') ?? []);
                        }
                        return $query->orderBy('name')->pluck('name', 'id')->all();
                    })
                    ->searchable(),
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
            'index' => Pages\ListProgressAlbums::route('/'),
            'create' => Pages\CreateProgressAlbum::route('/create'),
            'edit' => Pages\EditProgressAlbum::route('/{record}/edit'),
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
