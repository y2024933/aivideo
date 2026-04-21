<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
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

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = '頁面管理';

    protected static ?string $modelLabel = '頁面';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static ?string $pluralModelLabel = '頁面';

    protected static ?string $navigationGroup = '網站內容';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                Select::make('site_id')
                    ->label('網站')
                    ->relationship('site', 'name')
                    ->required()
                    ->visible(fn () => auth()->user()?->isSuperAdmin())
                    ->default(fn () => auth()->user()?->sites()->value('sites.id')),
                Toggle::make('is_published')
                    ->label('上架')
                    ->default(true)
                    ->inline(false),
            ]),
            TextInput::make('title')
                ->label('頁面標題')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label('頁面代碼')
                ->required()
                ->alphaDash()
                ->maxLength(255),
            Select::make('page_type')
                ->label('頁面類型')
                ->options([
                    'home' => '首頁',
                    'about' => '關於我們',
                    'projects' => '建築業績',
                    'news' => '最新消息',
                    'services' => '多元服務',
                    'progress' => '工程進度',
                    'contact' => '聯絡我們',
                    'content' => '一般內容頁',
                ])
                ->default('content')
                ->required(),
            Select::make('layout_key')
                ->label('版型')
                ->options([
                    'default' => '預設版型',
                    'builder-classic' => '經典建設版型',
                    'builder-editorial' => '編輯風格版型',
                ])
                ->default('default')
                ->required(),
            Textarea::make('summary')
                ->label('摘要')
                ->rows(4)
                ->columnSpanFull(),
            TiptapEditor::make('content')
                ->label('內容')
                ->disk('public')
                ->directory('editor-attachments/pages')
                ->columnSpanFull(),
            FileUpload::make('cover_image_path')
                ->label('封面圖')
                ->disk('public')
                ->directory('page-images')
                ->image()
                ->imageEditor()
                ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
            TextInput::make('cover_image_alt')->label('封面圖 Alt Text')->helperText('描述圖片內容，有助 SEO 與無障礙')->maxLength(255),
            FileUpload::make('gallery')
                ->label('頁面圖庫')
                ->disk('public')
                ->directory('page-gallery')
                ->image()
                ->multiple()
                ->reorderable()
                ->imageEditor()
                ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048))
                ->columnSpanFull(),
            TextInput::make('seo_title')
                ->label('SEO 標題')
                ->maxLength(255),
            Textarea::make('seo_description')
                ->label('SEO 描述')
                ->rows(3),
            Repeater::make('faq_items')
                ->label('FAQ 問答')
                ->schema([
                    TextInput::make('question')->label('問題')->required(),
                    Textarea::make('answer')->label('回答')->required()->rows(3),
                ])
                ->collapsible()
                ->columnSpanFull(),
            TextInput::make('sort_order')
                ->label('排序')
                ->numeric()
                ->default(0),
            DateTimePicker::make('published_at')
                ->label('發布時間'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                TextColumn::make('site.name')
                    ->label('網站')
                    ->toggleable(),
                TextColumn::make('title')
                    ->label('頁面標題')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('頁面代碼')
                    ->badge(),
                TextColumn::make('layout_key')
                    ->label('版型')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'default' => '預設版型',
                        'builder-classic' => '經典建設版型',
                        'builder-editorial' => '編輯風格版型',
                        default => $state ?? '-',
                    })
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->label('發布時間')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('最後更新')
                    ->since(),
                IconColumn::make('is_published')
                    ->label('上架')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('site_id')
                    ->label('網站')
                    ->relationship('site', 'name')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
                Tables\Filters\SelectFilter::make('page_type')
                    ->label('頁面類型')
                    ->options([
                        'home' => '首頁',
                        'about' => '關於我們',
                        'projects' => '建築業績',
                        'news' => '最新消息',
                        'services' => '多元服務',
                        'progress' => '工程進度',
                        'contact' => '聯絡我們',
                        'content' => '一般內容頁',
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
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
