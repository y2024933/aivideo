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

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            \Filament\Forms\Components\Grid::make(2)->schema([
                Select::make('site_id')
                    ->label('網站')
                    ->relationship('site', 'name')
                    ->required()
                    ->visible(fn () => auth()->user()?->isSuperAdmin())
                    ->default(fn () => auth()->user()?->sites()->value('sites.id'))
                    ->reactive()
                    ->afterStateUpdated(fn (callable $set) => $set('news_category_id', null)),
                Toggle::make('is_published')->label('上架')->default(true)->inline(false),
            ]),
            TextInput::make('title')->label('標題')->required()->maxLength(255),
            TextInput::make('slug')->label('頁面代碼')->required()->alphaDash(),
            Select::make('news_category_id')
                ->label('分類')
                ->options(function (callable $get) {
                    $siteId = $get('site_id') ?? auth()->user()?->sites()->value('sites.id');
                    if (! $siteId) return [];
                    return \App\Models\NewsCategory::where('site_id', $siteId)
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all();
                }),
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
            TextInput::make('featured_image_alt')->label('圖片 Alt Text')->helperText('描述圖片內容，有助 SEO 與無障礙')->maxLength(255),
            DateTimePicker::make('published_at')->label('發布時間'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')->label('網站')->searchable(auth()->user()?->isSuperAdmin() ?? false)->visible(fn () => auth()->user()?->isSuperAdmin())->toggleable(),
                TextColumn::make('title')->label('標題')->searchable()->sortable(),
                TextColumn::make('newsCategory.name')->label('分類'),
                TextColumn::make('published_at')->label('發布時間')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('updated_at')->label('最後更新')->since(),
                IconColumn::make('is_published')->label('上架')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('預覽')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (\App\Models\NewsArticle $record): string => '/preview/' . $record->site?->slug . '/news/' . $record->slug)
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('site_id')
                    ->label('網站')
                    ->relationship('site', 'name')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
                Tables\Filters\SelectFilter::make('news_category_id')
                    ->label('分類')
                    ->options(function () {
                        $user = auth()->user();
                        $query = \App\Models\NewsCategory::query();
                        if (! $user?->isSuperAdmin()) {
                            $query->whereIn('site_id', $user?->sites()->pluck('sites.id') ?? []);
                        }
                        return $query->orderBy('sort_order')->pluck('name', 'id')->all();
                    }),
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
