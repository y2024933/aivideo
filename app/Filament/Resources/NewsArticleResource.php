<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsArticleResource\Pages;
use App\Models\NewsArticle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use FilamentTiptapEditor\TiptapEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = '最新消息';

    protected static ?string $modelLabel = '最新消息';

    protected static ?string $pluralModelLabel = '最新消息';

    protected static ?string $navigationGroup = '網站內容';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('site_id')
                ->label('網站')
                ->relationship('site', 'name')
                ->required()
                ->visible(fn () => auth()->user()?->isSuperAdmin())
                ->default(fn () => auth()->user()?->sites()->value('sites.id')),
            TextInput::make('title')->label('標題')->required()->maxLength(255),
            TextInput::make('slug')->label('頁面代碼')->required()->alphaDash(),
            TextInput::make('category')->label('分類')->maxLength(255),
            Textarea::make('summary')->label('摘要')->rows(3),
            TiptapEditor::make('content')
                ->label('內容')
                ->disk('public')
                ->directory('editor-attachments/news')
                ->columnSpanFull(),
            FileUpload::make('featured_image_path')
                ->label('封面圖')
                ->disk('public')
                ->directory('news-images')
                ->image()
                ->imageEditor()
                ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
            DateTimePicker::make('published_at')->label('發布時間'),
            Toggle::make('is_published')->label('上架')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('標題')->searchable()->sortable(),
                TextColumn::make('category')->label('分類'),
                TextColumn::make('published_at')->label('發布時間')->dateTime('Y-m-d H:i'),
                TextColumn::make('updated_at')->label('最後更新')->since(),
                IconColumn::make('is_published')->label('上架')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('分類')
                    ->options(fn () => \App\Models\NewsArticle::query()
                        ->distinct()
                        ->whereNotNull('category')
                        ->pluck('category', 'category')
                        ->toArray()),
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
            'index' => Pages\ListNewsArticles::route('/'),
            'create' => Pages\CreateNewsArticle::route('/create'),
            'edit' => Pages\EditNewsArticle::route('/{record}/edit'),
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
