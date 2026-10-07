<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductImage;
use App\Services\ImageDownloader;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

final class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = '商品圖片';

    protected static ?string $modelLabel = '圖片';

    private const LICENSE_OPTIONS = [
        'unverified' => '未確認',
        'seller_authorized' => '賣家書面同意',
        'own_shot' => '自行拍攝',
        'platform_provided' => '平台素材中心提供',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('sort_order')
            ->poll('5s')
            ->columns([
                Tables\Columns\ImageColumn::make('local_path')->label('縮圖')->height(80)
                    ->getStateUsing(fn (ProductImage $r) => $r->remote_url ?: ($r->local_path ? url($r->local_path) : null)),
                Tables\Columns\TextColumn::make('source')->label('來源')->badge(),
                Tables\Columns\TextColumn::make('sort_order')->label('順序')->sortable(),
                Tables\Columns\TextColumn::make('dimensions')->label('尺寸')
                    ->getStateUsing(fn (ProductImage $r) => $r->width && $r->height ? "{$r->width} x {$r->height}" : '—'),
                Tables\Columns\TextColumn::make('license_status')->label('授權')->badge()
                    ->formatStateUsing(fn (?string $state) => self::LICENSE_OPTIONS[$state] ?? $state)
                    ->color(fn (?string $state) => $state === 'unverified' ? 'danger' : 'success'),
                Tables\Columns\IconColumn::make('is_primary')->label('主圖')->boolean(),
                Tables\Columns\ToggleColumn::make('is_selected')->label('選用'),
                Tables\Columns\TextColumn::make('status')->label('狀態')->badge(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('upload')
                    ->label('上傳圖片')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('files')->label('圖片檔')
                            ->multiple()->image()->disk('public')->directory('products')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)->required(),
                    ])
                    ->action(fn (array $data) => $this->storeUploads($data['files'])),

                Tables\Actions\Action::make('fetchExternal')
                    ->label('從外部連結抓圖')
                    ->icon('heroicon-o-globe-alt')
                    ->form([Forms\Components\TextInput::make('url')->label('圖片網址')->url()->required()])
                    ->action(fn (array $data) => $this->fetchExternal($data['url'])),
            ])
            ->actions([
                Tables\Actions\Action::make('makePrimary')
                    ->label('設為主圖')
                    ->icon('heroicon-o-star')
                    ->hidden(fn (ProductImage $record) => $record->is_primary)
                    ->action(function (ProductImage $record) {
                        $this->getOwnerRecord()->images()->update(['is_primary' => false]);
                        $record->update(['is_primary' => true, 'is_selected' => true]);
                    }),

                Tables\Actions\Action::make('markLicense')
                    ->label('標記授權')
                    ->icon('heroicon-o-shield-check')
                    ->form([
                        Forms\Components\Select::make('license_status')->label('授權狀態')->options(self::LICENSE_OPTIONS)->required()
                            ->helperText('蝦皮聯盟計畫約定條款 5.4(f) 禁止未經賣家書面同意自動化抓取其創作性素材，此欄位是風險留痕。'),
                        Forms\Components\TextInput::make('license_note')->label('備註')->maxLength(512)
                            ->helperText('例：2026-10-07 賣家 LINE 訊息同意，截圖存於 Drive。'),
                    ])
                    ->fillForm(fn (ProductImage $record) => $record->only(['license_status', 'license_note']))
                    ->action(fn (ProductImage $record, array $data) => $record->update($data)),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    /**
     * FileUpload 已寫入 public disk，這裡只補 metadata 並同步到 S3
     *
     * @param  array<int, string>  $paths
     */
    private function storeUploads(array $paths): void
    {
        $downloader = app(ImageDownloader::class);
        $next = (int) $this->getOwnerRecord()->images()->max('sort_order');

        foreach ($paths as $path) {
            $file = $downloader->adoptPublicFile($path);

            $this->getOwnerRecord()->images()->create([
                'sort_order' => ++$next,
                'source' => 'upload',
                'image_hash' => hash('sha256', $path),
                'local_path' => $file->localPath,
                'remote_url' => $file->remoteUrl,
                'width' => $file->width,
                'height' => $file->height,
                'bytes' => $file->bytes,
                'mime' => $file->mime,
                'status' => 'done',
                'license_status' => 'own_shot',
            ]);
        }

        Notification::make()->success()->title('已上傳 ' . count($paths) . ' 張圖並同步 S3')->send();
    }

    private function fetchExternal(string $url): void
    {
        try {
            $file = app(ImageDownloader::class)->download($url, 'products');
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('抓圖失敗')->body($e->getMessage())->send();

            return;
        }

        $this->getOwnerRecord()->images()->create([
            'sort_order' => (int) $this->getOwnerRecord()->images()->max('sort_order') + 1,
            'source' => 'external',
            'source_url' => Str::limit($url, 1000, ''),
            'image_hash' => hash('sha256', $url),
            'local_path' => $file->localPath,
            'remote_url' => $file->remoteUrl,
            'width' => $file->width,
            'height' => $file->height,
            'bytes' => $file->bytes,
            'mime' => $file->mime,
            'status' => 'done',
        ]);

        Notification::make()->success()->title('已抓取並同步 S3')->send();
    }
}
