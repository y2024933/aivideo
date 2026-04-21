<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteResource\Pages;
use App\Filament\Resources\SiteResource\RelationManagers;
use App\Models\Site;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = '網站管理';

    protected static ?string $modelLabel = '網站';

    protected static ?string $pluralModelLabel = '網站';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static ?string $navigationGroup = '網站管理';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('name')->label('網站名稱')->required()->maxLength(255),
                TextInput::make('brand_name')->label('品牌英文')->maxLength(255),
                TextInput::make('slug')->label('代碼')->required()->alphaDash()->unique(ignoreRecord: true),
                Select::make('theme_key')->label('主題')->required()->default('builder-classic')
                    ->options([
                        'builder-classic' => 'Classic — 白底極簡風',
                        'builder-editorial' => 'Editorial — 文藝風',
                    ]),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->directory('site-logos')
                    ->image()
                    ->imageEditor()
                    ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
                TextInput::make('logo_alt')->label('Logo Alt Text')->helperText('描述 Logo 內容，有助 SEO 與無障礙')->maxLength(255),
                FileUpload::make('favicon_path')
                    ->label('Favicon')
                    ->disk('public')
                    ->directory('site-favicons')
                    ->image()
                    ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
            ]),
            Section::make('首頁設定')->schema([
                TextInput::make('hero_content.eyebrow')->label('前導文字'),
                TextInput::make('hero_content.headline')->label('主標題')->required(),
                Textarea::make('hero_content.subheadline')->label('副標題')->rows(3),
                FileUpload::make('hero_content.background_image')
                    ->label('首頁背景圖')->disk('public')->directory('site-hero-images')
                    ->image()->imageEditor()
                    ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
            ])->columns(2),
            Section::make('SEO 預設')->schema([
                TextInput::make('seo_defaults.title')->label('SEO 標題'),
                Textarea::make('seo_defaults.description')->label('SEO 描述')->rows(3),
                TextInput::make('footer_content.opening_hours')->label('營業時間')
                    ->placeholder('週一至週五 09:00-18:00, 週六 09:00-12:00')
                    ->helperText('用於 Google 搜尋結果的結構化資料'),
                TextInput::make('footer_content.area_served')->label('服務區域')
                    ->placeholder('台中市, 新北市, 桃園市')
                    ->helperText('用於 Google 搜尋結果的結構化資料'),
            ]),
            Section::make('GEO（AI 搜尋優化）')->schema([
                Textarea::make('seo_defaults.llms_description')->label('AI 搜尋描述')
                    ->rows(4)
                    ->helperText('給 AI 搜尋引擎（ChatGPT、Perplexity、Gemini）看的公司簡介，自動產生 /llms.txt')
                    ->columnSpanFull(),
            ]),
            Section::make('AEO（答案引擎優化）')->schema([
                Repeater::make('seo_defaults.faq_items')
                    ->label('首頁 FAQ 問答')
                    ->schema([
                        TextInput::make('question')->label('問題')->required(),
                        Textarea::make('answer')->label('回答')->required()->rows(3),
                    ])
                    ->helperText('首頁顯示的常見問題，同時產生 FAQPage JSON-LD 爭取 Google 精選摘要')
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
            Section::make('頁尾資訊')->schema([
                TextInput::make('footer_content.address')->label('地址'),
                TextInput::make('footer_content.phone')->label('電話'),
                TextInput::make('footer_content.email')->label('Email'),
                TextInput::make('footer_content.copyright')->label('版權文字'),
                TextInput::make('social_links.facebook')->label('Facebook'),
                TextInput::make('social_links.instagram')->label('Instagram'),
                TextInput::make('social_links.line')->label('LINE'),
            ])->columns(2),
            Section::make('行銷追蹤')->schema([
                TextInput::make('tracking.ga4_id')->label('Google Analytics 4 ID')->placeholder('G-XXXXXXXXXX'),
                TextInput::make('tracking.gtm_id')->label('Google Tag Manager ID')->placeholder('GTM-XXXXXXX'),
                TextInput::make('tracking.meta_pixel_id')->label('Meta Pixel ID')->placeholder('1234567890'),
                TextInput::make('tracking.line_tag_id')->label('LINE Tag ID')->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
            ])->columns(2),
            Section::make('通知設定')->schema([
                Toggle::make('notification_settings.notify_enabled')
                    ->label('啟用表單通知')
                    ->helperText('啟用後，聯絡表單送出時會自動發送 Email 和 LINE 通知'),
                TagsInput::make('notification_settings.notify_emails')
                    ->label('通知 Email')
                    ->placeholder('輸入 Email 後按 Enter 新增')
                    ->helperText('收到聯絡表單時，通知這些 Email 地址')
                    ->splitKeys(['Enter', 'Tab', ','])
                    ->nestedRecursiveRules(['email'])
                    ->columnSpanFull(),
                Select::make('lineTargets')
                    ->label('LINE 推播對象')
                    ->relationship('lineTargets', 'display_name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->placeholder('選擇要接收通知的 LINE 群組或使用者')
                    ->helperText('選擇要接收通知的 LINE 群組或使用者（需先由 Super Admin 在「LINE 推播對象」分配）')
                    ->extraAttributes([
                        'class' => 'line-target-select',
                    ])
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('網站')->searchable()->sortable(),
                TextColumn::make('domains.domain')->label('網域'),
                TextColumn::make('theme_key')->label('主題')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'builder-classic' => 'Classic — 白底極簡風',
                        'builder-editorial' => 'Editorial — 文藝風',
                        default => $state,
                    }),
                IconColumn::make('is_active')->label('啟用')->boolean(),
            ])
            ->actions([
                Action::make('preview')
                    ->label('預覽')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Site $record): string => '/preview/' . $record->slug)
                    ->openUrlInNewTab(),
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
        return [
            RelationManagers\DomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'edit' => Pages\EditSite::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
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

        return $query->whereIn('id', $user->sites()->pluck('sites.id'));
    }
}
