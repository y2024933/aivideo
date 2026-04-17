<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteSettingResource\Pages;
use App\Models\SiteSetting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = '站台設定';

    protected static ?string $modelLabel = '站台設定';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static ?string $pluralModelLabel = '站台設定';

    protected static ?string $navigationGroup = '網站管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('site_id')
                ->label('網站')
                ->relationship('site', 'name')
                ->required()
                ->disabled(fn (?SiteSetting $record) => filled($record))
                ->visible(fn () => auth()->user()?->isSuperAdmin())
                ->default(fn () => auth()->user()?->sites()->value('sites.id')),
            Section::make('首頁模組')
                ->schema([
                    CheckboxList::make('homepage_sections')
                        ->label('顯示區塊')
                        ->options([
                            'hero' => '主視覺',
                            'about' => '關於摘要',
                            'services' => '多元服務',
                            'projects' => '建案',
                            'news' => '最新消息',
                            'progress' => '工程進度',
                            'contact' => '聯絡資訊',
                        ])
                        ->columns(4),
                ]),
            Section::make('Hero 設定')
                ->schema([
                    TextInput::make('hero_content.eyebrow')->label('前導文字'),
                    TextInput::make('hero_content.headline')->label('主標題')->required(),
                    Textarea::make('hero_content.subheadline')->label('副標題')->rows(3),
                    TextInput::make('hero_content.cta_label')->label('CTA 文字'),
                    TextInput::make('hero_content.cta_link')->label('CTA 連結'),
                    FileUpload::make('hero_content.background_image')
                        ->label('Hero 背景圖')
                        ->disk('public')
                        ->directory('site-hero-images')
                        ->image()
                        ->imageEditor()
                        ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
                    TextInput::make('hero_content.video_url')->label('Hero 影片網址（mp4）'),
                ])
                ->columns(2),
            Section::make('首頁關於摘要')
                ->schema([
                    TextInput::make('about_content.eyebrow')->label('前導文字'),
                    TextInput::make('about_content.headline')->label('標題'),
                    Textarea::make('about_content.summary')
                        ->label('摘要')
                        ->rows(4)
                        ->columnSpanFull(),
                    Repeater::make('about_content.highlights')
                        ->label('亮點卡片')
                        ->schema([
                            TextInput::make('title')->label('標題')->required(),
                            TextInput::make('description')->label('說明'),
                        ])
                        ->defaultItems(3)
                        ->columns(2)
                        ->columnSpanFull(),
                    Repeater::make('about_content.team_members')
                        ->label('經營團隊')
                        ->schema([
                            TextInput::make('name')->label('姓名')->required(),
                            TextInput::make('title')->label('職稱')->required(),
                            TextInput::make('company')->label('公司'),
                            Textarea::make('description')->label('介紹')->rows(2),
                            FileUpload::make('photo')
                                ->label('頭像')
                                ->disk('public')
                                ->directory('team-photos')
                                ->image()
                                ->imageEditor()
                                ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
                        ])
                        ->defaultItems(2)
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('多元服務')
                ->schema([
                    TextInput::make('service_content.eyebrow')->label('前導文字'),
                    TextInput::make('service_content.headline')->label('標題'),
                    Textarea::make('service_content.summary')
                        ->label('摘要')
                        ->rows(4),
                    TextInput::make('service_content.cta_label')->label('按鈕文字'),
                    Repeater::make('service_content.cards')
                        ->label('服務卡片')
                        ->schema([
                            TextInput::make('number')->label('序號'),
                            TextInput::make('title')->label('標題')->required(),
                            Textarea::make('description')->label('說明')->rows(3),
                        ])
                        ->defaultItems(3)
                        ->columns(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('聯絡區塊')
                ->schema([
                    TextInput::make('contact_content.eyebrow')->label('前導文字'),
                    TextInput::make('contact_content.headline')->label('標題'),
                    Textarea::make('contact_content.summary')
                        ->label('摘要')
                        ->rows(4),
                    TextInput::make('contact_content.cta_label')->label('按鈕文字'),
                    TagsInput::make('contact_content.inquiry_types')
                        ->label('留言類別')
                        ->placeholder('輸入後按 Enter')
                        ->columnSpanFull(),
                    Repeater::make('contact_content.highlights')
                        ->label('聯絡亮點')
                        ->schema([
                            TextInput::make('title')->label('標題')->required(),
                            TextInput::make('description')->label('說明')->required(),
                        ])
                        ->defaultItems(3)
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('SEO 預設')
                ->schema([
                    TextInput::make('seo_defaults.title')->label('SEO 標題'),
                    Textarea::make('seo_defaults.description')->label('SEO 描述')->rows(3),
                ]),
            Section::make('頁尾資訊')
                ->schema([
                    TextInput::make('footer_content.address')->label('地址'),
                    TextInput::make('footer_content.phone')->label('電話'),
                    TextInput::make('footer_content.email')->label('Email'),
                    TextInput::make('footer_content.copyright')->label('版權文字'),
                    TextInput::make('social_links.facebook')->label('Facebook'),
                    TextInput::make('social_links.instagram')->label('Instagram'),
                    TextInput::make('social_links.line')->label('LINE'),
                ])
                ->columns(2),
            Section::make('行銷追蹤')
                ->schema([
                    TextInput::make('tracking.ga4_id')->label('Google Analytics 4 ID')->placeholder('G-XXXXXXXXXX'),
                    TextInput::make('tracking.gtm_id')->label('Google Tag Manager ID')->placeholder('GTM-XXXXXXX'),
                    TextInput::make('tracking.meta_pixel_id')->label('Meta Pixel ID')->placeholder('1234567890'),
                    TextInput::make('tracking.line_tag_id')->label('LINE Tag ID')->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),
            Section::make('轉換工具')
                ->schema([
                    TextInput::make('tracking.line_official_url')->label('LINE 官方帳號連結')->placeholder('https://line.me/R/ti/p/@xxxxx')->url(),
                    TextInput::make('tracking.phone_cta')->label('一鍵撥打電話號碼')->placeholder('04-8955531'),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')->label('網站')->searchable(),
                TextColumn::make('hero_content.headline')->label('首頁主標')->limit(40),
                TextColumn::make('updated_at')->label('最後更新')->since(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteSettings::route('/'),
            'create' => Pages\CreateSiteSetting::route('/create'),
            'edit' => Pages\EditSiteSetting::route('/{record}/edit'),
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
