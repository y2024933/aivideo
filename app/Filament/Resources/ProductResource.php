<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Data\ComplianceFinding;
use App\Data\ComplianceReport;
use App\Enums\AudioMode;
use App\Enums\KenBurns;
use App\Enums\ProductStatus;
use App\Enums\VideoProvider;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use App\Models\Shot;
use App\Services\Compliance\AdComplianceChecker;
use App\Services\Compliance\TraditionalChineseValidator;
use App\Services\ImageDownloader;
use App\Services\Llm\ScriptFields;
use App\Services\Llm\ScriptWriterFactory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?string $navigationLabel = '商品影片';

    protected static ?string $modelLabel = '商品';

    protected static ?string $pluralModelLabel = '商品';

    /** 與 remotion/src/ProductVideo.jsx 的 getPresentation() 一對一 */
    public const TRANSITIONS = [
        'cut' => '硬切',
        'crossfade' => '淡入淡出',
        'slideLeft' => '左滑入',
        'slideRight' => '右滑入',
        'slideUp' => '上滑入',
        'slideDown' => '下滑入',
        'wipeLeft' => '左擦除',
        'wipeRight' => '右擦除',
        'wipeUp' => '上擦除',
        'wipeDown' => '下擦除',
        'flipHorizontal' => '水平翻轉',
        'flipVertical' => '垂直翻轉',
        'clockWipe' => '時鐘擦除',
    ];

    /** BGM 的版權警示。寫死在程式裡而不是 config，因為這是不該被關掉的提醒。 */
    public const BGM_WARNING = '⚠️ 不得使用有版權的流行音樂。蝦皮與第三方音樂辨識會自動下架並可能停權。'
        . '建議來源：Pixabay Music、Free Music Archive（CC0/CC-BY）或付費授權庫。'
        . '注意 YouTube Audio Library 的授權僅限 YouTube 使用，不可搬到蝦皮。';

    /** Azure TTS zh-TW 聲音 */
    public const VOICES = [
        'zh-TW-HsiaoChenNeural' => '曉臻（女聲・親切）',
        'zh-TW-HsiaoYuNeural' => '曉雨（女聲・活潑）',
        'zh-TW-YunJheNeural' => '雲哲（男聲・沉穩）',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('商品基本資料')->schema([
                Forms\Components\TextInput::make('title')->label('商品標題')->required()->maxLength(512)->columnSpanFull(),
                Forms\Components\TextInput::make('source_url')->label('商品來源連結')->url()->maxLength(1024),
                Forms\Components\TextInput::make('affiliate_url')->label('分潤連結')->url()->maxLength(1024)
                    ->helperText('影片 CTA 與貼文都會帶這條，沒有它等於白做。'),
                Forms\Components\TextInput::make('brand')->label('品牌'),
                Forms\Components\TextInput::make('category')->label('分類'),
                Forms\Components\Select::make('compliance_profile')->label('合規類別')
                    ->options(fn () => collect(config('compliance.profiles'))->keys()->mapWithKeys(fn (string $k) => [$k => $k])->all())
                    ->helperText('決定套用哪一組廣告法規檢查，填錯會漏掉該品類的 blocking 規則。'),
                Forms\Components\TextInput::make('price')->label('售價')->numeric()->prefix('NT$'),
                Forms\Components\TextInput::make('price_before_discount')->label('原價')->numeric()->prefix('NT$'),
                Forms\Components\TextInput::make('rating_star')->label('評分')->numeric()->step(0.01),
                Forms\Components\TextInput::make('rating_count')->label('評價數')->numeric(),
                Forms\Components\TextInput::make('historical_sold')->label('歷史銷量')->numeric(),
                Forms\Components\Textarea::make('description')->label('商品描述')->rows(4)->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('影片設定')->schema([
                Forms\Components\TextInput::make('video_length_seconds')->label('影片長度（秒）')->numeric()->default(30),
                Forms\Components\Select::make('audio_mode')->label('音訊模式')->options(AudioMode::class)->default('none')->live(),
                Forms\Components\Select::make('voice_id_preferred')->label('配音聲音')->options(self::VOICES)
                    ->visible(fn (Forms\Get $get) => $get('audio_mode') === AudioMode::Tts->value),
                Forms\Components\Select::make('video_provider')->label('動畫供應商')->options(VideoProvider::class)->default('none'),
                Forms\Components\Select::make('global_transition')->label('全域轉場')->options(self::TRANSITIONS)->default('crossfade'),
                Forms\Components\Select::make('default_ken_burns')->label('預設運鏡')->options(KenBurns::class)->default('auto'),
                Forms\Components\Select::make('script_provider')->label('寫稿模型')
                    ->options(fn () => app(ScriptWriterFactory::class)->availableOptions())
                    ->placeholder(fn () => '使用全域預設（' . app(ScriptWriterFactory::class)->default()->getLabel() . '）')
                    ->helperText('Gemini 走免費額度但文字稍弱；Claude 每支約 $0.034 美金、稿子較穩。留空就用 .env 的 SCRIPT_PROVIDER。'),

                Forms\Components\Placeholder::make('bgm_current')->label('目前背景音樂')
                    ->visible(fn (?Product $record) => filled($record?->bgm_url))
                    ->content(fn (?Product $record) => new HtmlString(
                        '<audio controls src="' . e($record->bgm_remote_url ?: url($record->bgm_url)) . '" style="width:100%"></audio>'
                    ))
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('bgm_upload')->label('上傳背景音樂')
                    ->disk('public')->directory('bgm')
                    ->acceptedFileTypes(['audio/mpeg', 'audio/mp4', 'audio/wav'])
                    ->maxSize(20480)
                    ->helperText(self::BGM_WARNING)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('bgm_volume')->label('背景音樂音量')
                    ->numeric()->minValue(0)->maxValue(1)->step(0.05)->default(0.25)
                    ->helperText('0–1。有配音時建議 0.15–0.25，否則人聲會被蓋掉。'),
                Forms\Components\TextInput::make('bgm_license_note')->label('音樂來源與授權')->maxLength(512)
                    // bgm_url 非空就強制填寫：這是平台申訴與法規舉證的唯一依據，事後補不回來
                    ->required(fn (Forms\Get $get, ?Product $record) => filled($record?->bgm_url) || filled($get('bgm_upload')))
                    ->helperText('填「來源 + 授權類型」，例：Pixabay Music / CC0、Epidemic Sound 訂閱 #12345。'),
            ])->columns(2),

            Forms\Components\Section::make('文案')->schema([
                Forms\Components\Textarea::make('disclosure_prefix')->label('分潤揭露前綴')->required()->rows(2)->maxLength(255)
                    ->default(fn () => config('compliance.disclosure_prefix'))
                    ->helperText('公平會《網路廣告處理原則》把推廣者視為廣告主，分潤揭露是法規必填，不得留空。')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('caption')->label('貼文內容')->rows(5)->columnSpanFull(),
                Forms\Components\TagsInput::make('hashtags')->label('Hashtags')->columnSpanFull(),
            ]),

            Forms\Components\Section::make('合規檢查')->schema([
                Forms\Components\ViewField::make('compliance_panel')
                    ->label('')
                    ->view('filament.components.compliance-panel')
                    // ViewField 預設會被 dehydrate 成表單狀態的一個 key，不關掉的話存檔時
                    // 會變成 UPDATE products SET compliance_panel = ... → Unknown column
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ])->hiddenOn('create')->collapsible(),

            Forms\Components\Section::make('目前狀態')->schema([
                Forms\Components\ViewField::make('status_poller')
                    ->label('')
                    ->view('filament.components.status-poller')
                    ->dehydrated(false)
                    ->visible(fn ($record) => $record?->status?->isProcessing() ?? false)
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('status_label')->label('狀態')
                    ->content(fn ($record) => $record?->status?->getLabel() ?? '新商品'),
                Forms\Components\Placeholder::make('total_cost')->label('累計成本')
                    ->content(fn ($record) => '$' . number_format($record?->totalCostUsd() ?? 0, 4) . ' USD'),
                Forms\Components\Placeholder::make('status_message_text')->label('訊息')
                    ->visible(fn ($record) => filled($record?->status_message))
                    ->content(fn ($record) => new HtmlString('<span class="text-danger-600 dark:text-danger-400">' . e($record->status_message) . '</span>')),
                Forms\Components\Placeholder::make('approval_blockers')->label('核准前缺項')
                    ->visible(fn ($record) => $record?->status === ProductStatus::ProductPendingReview && self::approvalBlockers($record) !== [])
                    ->content(fn ($record) => new HtmlString('<ul class="list-disc ps-5 text-danger-600 dark:text-danger-400">'
                        . collect(self::approvalBlockers($record))->map(fn (string $b) => '<li>' . e($b) . '</li>')->implode('')
                        . '</ul>'))
                    ->columnSpanFull(),
            ])->columns(3)->hiddenOn('create'),

            Forms\Components\Section::make('成品影片')->schema([
                Forms\Components\Placeholder::make('final_video_preview')->label('')->content(function ($record) {
                    if (! $record?->final_video_url) {
                        return $record?->render_id ? '影片渲染中，請稍候…' : '尚未渲染。';
                    }
                    $url = e($record->final_video_remote_url ?: url($record->final_video_url));

                    return new HtmlString(
                        "<div style='text-align:center'><video controls style='max-width:100%;max-height:500px;border-radius:8px'><source src='{$url}' type='video/mp4'></video>"
                        . "<p style='margin-top:12px'><a href='{$url}' download style='color:#4f46e5;text-decoration:underline'>下載影片</a></p></div>"
                    );
                }),
            ])->hiddenOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('商品標題')->searchable()->limit(40)->sortable(),
                Tables\Columns\TextColumn::make('status')->label('狀態')->badge(),
                Tables\Columns\TextColumn::make('compliance_profile')->label('合規類別')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('price')->label('售價')->money('TWD'),
                Tables\Columns\TextColumn::make('images_count')->label('圖片')->counts('images'),
                Tables\Columns\TextColumn::make('shots_count')->label('鏡頭')->counts('shots'),
                Tables\Columns\TextColumn::make('cost_usd')->label('成本')->money('USD'),
                Tables\Columns\TextColumn::make('created_at')->label('建立時間')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('狀態')->options(ProductStatus::class)->multiple(),
                Tables\Filters\SelectFilter::make('compliance_profile')->label('合規類別')
                    ->options(fn () => collect(config('compliance.profiles'))->keys()->mapWithKeys(fn (string $k) => [$k => $k])->all()),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    /**
     * checkpoint ① 的 gate：少任一項都不給核准。
     * remote_url 是 Remotion Lambda 唯一能讀到的圖片來源，沒有就等於渲染必 404。
     *
     * @return array<int, string>
     */
    public static function approvalBlockers(?Product $product): array
    {
        if (! $product) {
            return ['商品尚未建立'];
        }

        $selected = $product->selectedImages()->get();
        $blockers = [];

        if ($selected->count() < 2) {
            $blockers[] = '勾選的圖片不足 2 張';
        }

        if ($missing = $selected->whereNull('remote_url')->count()) {
            $blockers[] = "有 {$missing} 張圖尚未同步到 S3";
        }

        foreach (['title' => '商品標題', 'affiliate_url' => '分潤連結', 'disclosure_prefix' => '分潤揭露前綴'] as $field => $label) {
            if (blank($product->{$field})) {
                $blockers[] = $label;
            }
        }

        return $blockers;
    }

    /**
     * checkpoint ② 的 gate：重跑一次合規檢查（不寫 DB）。
     *
     * ⚠️ 一定要重跑而不是讀 products.compliance_report：operator 在 checkpoint ② 可以
     * 直接改字幕與貼文，舊報告描述的是 LLM 當初寫的字，不是現在要上線的字。
     */
    public static function checkScript(Product $product): ComplianceReport
    {
        $checker = app(AdComplianceChecker::class);

        return $checker->mergeAcknowledged(
            $checker->check(ScriptFields::fromProduct($product), (string) ($product->compliance_profile ?: 'general')),
            $product->compliance_report,
        );
    }

    /**
     * 核准腳本前的缺項。空陣列才可以進 checkpoint ②。
     *
     * @return array<int, string>
     */
    public static function scriptApprovalBlockers(Product $product, ?ComplianceReport $report = null): array
    {
        $report ??= self::checkScript($product);
        $shots = $product->shots()->get();
        $blockers = [];

        if ($shots->isEmpty()) {
            $blockers[] = '尚未產生任何鏡頭';
        }

        if ($report->profileBlocked) {
            $blockers[] = '此分類不開放製作影片：' . $report->profileBlockedReason;
        }

        if ($blocking = count($report->blocking())) {
            $blockers[] = "有 {$blocking} 項 blocking 違規必須修掉（不可略過）";
        }

        if ($pending = count($report->unacknowledgedWarnings())) {
            $blockers[] = "有 {$pending} 項警告尚未逐條確認";
        }

        // 報告是以「現在 DB 裡的字」重跑的，所以旗標與報告理論上一致；
        // 旗標仍要看，才擋得住「報告重跑過但鏡頭旗標沒同步」的狀況。
        if ($shots->contains(fn (Shot $shot) => $shot->subtitle_has_simplified || $shot->voiceover_has_simplified)) {
            $blockers[] = '有鏡頭字幕或配音稿含簡體字';
        }

        if (blank($product->disclosure_prefix)) {
            $blockers[] = '未設定聯盟行銷揭露前綴';
        }

        return $blockers;
    }

    /** 把報告寫回商品與鏡頭（compliance_flags、簡體字旗標）。 */
    public static function storeComplianceReport(Product $product, ComplianceReport $report): void
    {
        $zhTw = app(TraditionalChineseValidator::class);
        $byShot = $report->byShot();

        $product->update([
            'compliance_report' => $report->toArray(),
            'compliance_passed' => $report->passed(),
            'compliance_checked_at' => $report->checkedAt,
            'compliance_rules_version' => $report->rulesVersion,
            'compliance_rules_fingerprint' => $report->rulesFingerprint,
        ]);

        foreach ($product->shots()->get() as $shot) {
            /** @var Shot $shot */
            $shot->update([
                'subtitle_has_simplified' => $zhTw->findSimplifiedChars((string) $shot->subtitle) !== [],
                'voiceover_has_simplified' => $zhTw->findSimplifiedChars((string) $shot->voiceover_text) !== [],
                'compliance_flags' => array_map(fn (ComplianceFinding $finding) => $finding->toArray(), $byShot[$shot->shot_id] ?? []),
            ]);
        }
    }

    /** 已存報告的 findings 可確認清單的 key（blocking 永遠不在裡面）。 */
    public static function findingKey(ComplianceFinding|array $finding): string
    {
        $finding = is_array($finding) ? $finding : $finding->toArray();

        return implode('|', [$finding['ruleId'] ?? '', $finding['field'] ?? '', $finding['matched'] ?? '']);
    }

    /**
     * 把 FileUpload 寫到 public disk 的 BGM 補同步到 S3，並寫回 bgm_url / bgm_remote_url。
     *
     * ⚠️ bgm_upload 不是 DB 欄位，Product 又是 $guarded = []，不 unset 會直接撞 SQL。
     * 刻意複用 ImageDownloader::adoptPublicFile()：它就是「public disk → 補 metadata → S3 雙寫」
     * 這條路徑，為音檔再抄一份只會多一處會忘記同步 S3 的地方（Lambda 讀不到 localhost）。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function adoptBgmUpload(array $data): array
    {
        $upload = Arr::first(Arr::wrap($data['bgm_upload'] ?? []));
        unset($data['bgm_upload']);

        if (blank($upload)) {
            return $data;
        }

        $file = app(ImageDownloader::class)->adoptPublicFile((string) $upload);

        return [...$data, 'bgm_url' => $file->localPath, 'bgm_remote_url' => $file->remoteUrl];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\ShotsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
