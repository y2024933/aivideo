<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgressUpdateResource\Pages;
use App\Models\ProgressUpdate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use FilamentTiptapEditor\TiptapEditor;
use Illuminate\Database\Eloquent\Builder;

class ProgressUpdateResource extends Resource
{
    protected static ?string $model = ProgressUpdate::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = '工程進度';

    protected static ?string $modelLabel = '工程進度';

    protected static ?string $pluralModelLabel = '工程進度';

    protected static ?string $navigationGroup = '網站內容';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('site_id')
                ->label('網站')
                ->relationship('site', 'name')
                ->required()
                ->visible(fn () => auth()->user()?->isSuperAdmin())
                ->default(fn () => auth()->user()?->sites()->value('sites.id')),
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
            Grid::make(2)->schema([
                TextInput::make('title')->label('標題')->required()->maxLength(255),
                Toggle::make('is_published')->label('上架')->default(true)->inline(false),
            ]),
            Textarea::make('summary')->label('摘要')->rows(3)->columnSpanFull(),
            TiptapEditor::make('content')
                ->label('詳細內容')
                ->disk('public')
                ->directory('editor-attachments/progress')
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
                TextColumn::make('title')
                    ->label('標題')
                    ->searchable()
                    ->limit(30),
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
                TextColumn::make('reported_at')
                    ->label('施工日期')
                    ->date('Y-m-d')
                    ->sortable(),
                IconColumn::make('is_published')->label('上架')->boolean(),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label('建案')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListProgressUpdates::route('/'),
            'create' => Pages\CreateProgressUpdate::route('/create'),
            'edit' => Pages\EditProgressUpdate::route('/{record}/edit'),
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
