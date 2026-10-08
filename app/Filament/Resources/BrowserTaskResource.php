<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BrowserStrategy;
use App\Enums\BrowserTaskStatus;
use App\Enums\BrowserTaskType;
use App\Filament\Resources\BrowserTaskResource\Pages;
use App\Jobs\ScrapeShopeeProductJob;
use App\Models\BrowserTask;
use App\Models\Product;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * 瀏覽器任務的稽核畫面（唯讀 + 手動重試）。
 *
 * 存在的理由很單純：抓取失敗時「為什麼」只能從當下的截圖看出來（人機驗證？
 * 商品下架？蝦皮改版？），而 browser 容器的 artifacts 目錄會隨容器重建消失，
 * 所以截圖與 trace 都搬到 S3 並在這裡給連結。
 */
final class BrowserTaskResource extends Resource
{
    protected static ?string $model = BrowserTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = '瀏覽器任務';

    protected static ?string $modelLabel = '瀏覽器任務';

    protected static ?string $pluralModelLabel = '瀏覽器任務';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('類型')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('狀態')->badge()->sortable(),
                Tables\Columns\TextColumn::make('strategy_used')->label('策略')->badge()
                    ->color(fn (?BrowserStrategy $state) => $state === BrowserStrategy::Dom ? 'warning' : 'gray')
                    // 降級（dom）代表資料來源不可靠，必須在列表就看得出來
                    ->tooltip(fn (?BrowserStrategy $state) => $state === BrowserStrategy::Dom ? 'DOM 降級解析，欄位可能缺漏，請人工核對' : null),
                Tables\Columns\TextColumn::make('attempts')->label('嘗試')
                    ->formatStateUsing(fn (BrowserTask $record) => "{$record->attempts}/{$record->max_attempts}"),
                Tables\Columns\TextColumn::make('duration_ms')->label('耗時')
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : number_format($state / 1000, 1) . ' 秒')
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_code')->label('錯誤碼')->badge()->color('danger')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('建立時間')->dateTime('Y-m-d H:i:s')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('類型')->options(BrowserTaskType::class)->multiple(),
                Tables\Filters\SelectFilter::make('status')->label('狀態')->options(BrowserTaskStatus::class)->multiple(),
                Tables\Filters\SelectFilter::make('strategy_used')->label('策略')->options(BrowserStrategy::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('查看'),
                self::retryAction(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('任務')->schema([
                Infolists\Components\TextEntry::make('type')->label('類型')->badge(),
                Infolists\Components\TextEntry::make('status')->label('狀態')->badge(),
                Infolists\Components\TextEntry::make('strategy_used')->label('策略')->badge()->placeholder('—'),
                Infolists\Components\TextEntry::make('profile')->label('瀏覽器 profile')->placeholder('未登入 context'),
                Infolists\Components\TextEntry::make('attempts')->label('嘗試次數')
                    ->formatStateUsing(fn (BrowserTask $record) => "{$record->attempts}/{$record->max_attempts}"),
                Infolists\Components\TextEntry::make('duration_ms')->label('耗時')
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : number_format($state / 1000, 2) . ' 秒'),
                Infolists\Components\TextEntry::make('started_at')->label('開始')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                Infolists\Components\TextEntry::make('finished_at')->label('結束')->dateTime('Y-m-d H:i:s')->placeholder('—'),
            ])->columns(4),

            Infolists\Components\Section::make('降級警示')
                ->visible(fn (BrowserTask $record) => $record->strategy_used === BrowserStrategy::Dom)
                ->schema([
                    Infolists\Components\TextEntry::make('degraded_warning')->label('')
                        ->state('⚠️ 這筆資料是 DOM 降級解析的結果（沒攔到官方 API）。價格、評分、規格可能缺漏或錯位，'
                            . '商品不會自動放行 checkpoint ①，請逐欄人工核對。'),
                ]),

            Infolists\Components\Section::make('錯誤')
                ->visible(fn (BrowserTask $record) => filled($record->error_message) || filled($record->error_code))
                ->schema([
                    Infolists\Components\TextEntry::make('error_code')->label('錯誤碼')->badge()->color('danger'),
                    Infolists\Components\TextEntry::make('error_message')->label('錯誤訊息')->columnSpanFull(),
                ])->columns(2),

            Infolists\Components\Section::make('除錯素材')
                ->visible(fn (BrowserTask $record) => filled($record->screenshot_path) || filled($record->trace_path) || filled($record->har_path))
                ->schema([
                    Infolists\Components\ImageEntry::make('screenshot_path')->label('失敗截圖')
                        ->visible(fn (BrowserTask $record) => filled($record->screenshot_path))
                        ->state(fn (BrowserTask $record) => self::artifactUrl($record->screenshot_path))
                        ->height(420)
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('trace_path')->label('Playwright trace')
                        ->visible(fn (BrowserTask $record) => filled($record->trace_path))
                        ->html()
                        ->formatStateUsing(fn (BrowserTask $record) => new HtmlString(
                            '<a class="text-primary-600 underline" target="_blank" href="' . e((string) self::artifactUrl($record->trace_path)) . '">下載 trace.zip</a>'
                            . '<span class="text-gray-500"> → 用 npx playwright show-trace 開啟</span>'
                        )),
                    Infolists\Components\TextEntry::make('har_path')->label('HAR')
                        ->visible(fn (BrowserTask $record) => filled($record->har_path))
                        ->html()
                        ->formatStateUsing(fn (BrowserTask $record) => new HtmlString(
                            '<a class="text-primary-600 underline" target="_blank" href="' . e((string) self::artifactUrl($record->har_path)) . '">下載 HAR</a>'
                        )),
                ])->columns(2),

            Infolists\Components\Section::make('payload 與結果')->collapsed()->schema([
                Infolists\Components\KeyValueEntry::make('payload')->label('送出的參數')->columnSpanFull(),
                Infolists\Components\TextEntry::make('result')->label('回傳資料')->columnSpanFull()
                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '—'),
            ]),
        ]);
    }

    /** 人工重試：帶 ignoreTimeWindow，因為人就在電腦前面，風險自己承擔 */
    public static function retryAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('retryScrape')
            ->label('重新抓取')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('會忽略執行時段限制立刻重抓（每小時次數上限仍然有效）。')
            ->visible(fn (BrowserTask $record) => $record->type === BrowserTaskType::ShopeeScrapeProduct
                && $record->subject_type === (new Product)->getMorphClass()
                && filled($record->subject_id))
            ->action(function (BrowserTask $record) {
                ScrapeShopeeProductJob::dispatch((string) $record->subject_id, ignoreTimeWindow: true);
                Notification::make()->success()->title('已排入重新抓取')->send();
            });
    }

    private static function artifactUrl(?string $path): ?string
    {
        return blank($path) ? null : Storage::disk((string) config('services.browser.artifacts_disk', 's3'))->url($path);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrowserTasks::route('/'),
            'view' => Pages\ViewBrowserTask::route('/{record}'),
        ];
    }
}
