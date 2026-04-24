<?php

namespace App\Filament\Resources;

use App\Enums\PageTemplate;
use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Closure;
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
use Filament\Forms\Get;
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

    private static function fieldVisible(string $field): Closure
    {
        return fn (Get $get) => in_array($field, PageTemplate::tryFrom($get('page_type'))?->availableFields() ?? []);
    }

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
            Select::make('page_type')
                ->label('頁面模板')
                ->options(PageTemplate::options())
                ->default('content')
                ->required()
                ->live(onBlur: true),
            TextInput::make('title')
                ->label('頁面標題')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label('頁面代碼')
                ->required()
                ->alphaDash()
                ->maxLength(255)
                ->notIn([
                    'admin', 'dashboard', 'profile', 'captcha', 'login', 'logout',
                    'register', 'forgot-password', 'reset-password', 'verify-email',
                    'confirm-password', 'preview', 'robots.txt', 'sitemap.xml', 'llms.txt',
                    'api', 'filament',
                ])
                ->validationMessages(['not_in' => '此代碼為系統保留字，請使用其他名稱。'])
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('site_id', $get('site_id'))),
            TextInput::make('sort_order')
                ->label('排序')
                ->numeric()
                ->default(0),
            DateTimePicker::make('published_at')
                ->label('發布時間'),
            Textarea::make('summary')
                ->label('摘要')
                ->rows(4)
                ->columnSpanFull()
                ->visible(self::fieldVisible('summary')),
            TiptapEditor::make('content')
                ->label('內容')
                ->disk('public')
                ->directory('editor-attachments/pages')
                ->columnSpanFull()
                ->visible(self::fieldVisible('content')),
            FileUpload::make('gallery')
                ->label('頁面圖庫')
                ->disk('public')
                ->directory('page-gallery')
                ->image()
                ->multiple()
                ->reorderable()
                ->imageEditor()
                ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048))
                ->columnSpanFull()
                ->visible(self::fieldVisible('gallery')),
            \Filament\Forms\Components\Section::make('SEO 設定')
                ->schema([
                    TextInput::make('seo_title')
                        ->label('SEO 標題')
                        ->maxLength(255),
                    Textarea::make('seo_description')
                        ->label('SEO 描述')
                        ->rows(3),
                    FileUpload::make('cover_image_path')
                        ->label('分享預覽圖')
                        ->helperText('社群媒體分享時顯示的縮圖（og:image）')
                        ->disk('public')
                        ->directory('page-images')
                        ->image()
                        ->imageEditor()
                        ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
                ])
                ->columns(2),
            Repeater::make('faq_items')
                ->label('FAQ 問答')
                ->schema([
                    TextInput::make('question')->label('問題')->required(),
                    Textarea::make('answer')->label('回答')->required()->rows(3),
                ])
                ->collapsible()
                ->columnSpanFull()
                ->visible(self::fieldVisible('faq_items')),
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
                TextColumn::make('page_type')
                    ->label('模板')
                    ->formatStateUsing(fn ($state) => $state instanceof PageTemplate ? $state->label() : (PageTemplate::tryFrom($state)?->label() ?? $state)),
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
                    ->label('頁面模板')
                    ->options(PageTemplate::options()),
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
