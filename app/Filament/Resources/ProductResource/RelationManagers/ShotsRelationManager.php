<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Enums\KenBurns;
use App\Enums\ShotRole;
use App\Filament\Resources\ProductResource;
use App\Models\ProductImage;
use App\Models\Shot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class ShotsRelationManager extends RelationManager
{
    protected static string $relationship = 'shots';

    protected static ?string $title = '分鏡';

    protected static ?string $modelLabel = '鏡頭';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('shot_id')->label('鏡頭編號')->required()->maxLength(8)->default('S01'),
            Forms\Components\TextInput::make('shot_order')->label('順序')->numeric()->required()->default(1),
            Forms\Components\Select::make('role')->label('角色')->options(ShotRole::class),
            Forms\Components\TextInput::make('duration_seconds')->label('秒數')->numeric()->step(0.1)->default(3.5),
            Forms\Components\Textarea::make('subtitle')->label('字幕')->rows(2)->columnSpanFull(),
            Forms\Components\Textarea::make('voiceover_text')->label('配音稿')->rows(2)->columnSpanFull(),
            Forms\Components\Select::make('ken_burns')->label('運鏡')->options(KenBurns::class)
                ->placeholder('繼承商品設定'),
            // 舊專案這欄資料流通但沒有輸入介面，等於付了實作成本卻用不到
            Forms\Components\Select::make('transition')->label('轉場')->options(ProductResource::TRANSITIONS)
                ->placeholder('繼承商品的全域轉場')
                ->helperText('留空 = 使用商品的全域轉場設定。'),
            Forms\Components\Select::make('fit')->label('縮放方式')
                ->options(['contain' => 'contain（完整顯示，留黑邊）', 'cover' => 'cover（填滿，可能裁切）'])->default('contain'),
            Forms\Components\Textarea::make('scene_description')->label('場景描述')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('shot_id')
            ->defaultSort('shot_order')
            ->poll('5s')
            ->columns([
                Tables\Columns\TextColumn::make('shot_id')->label('編號')->sortable(),
                Tables\Columns\TextColumn::make('shot_order')->label('順序')->sortable(),
                Tables\Columns\TextColumn::make('role')->label('角色')->badge(),
                Tables\Columns\TextColumn::make('duration_seconds')->label('秒數'),
                Tables\Columns\ImageColumn::make('image_remote_url')->label('素材')->height(64),
                Tables\Columns\TextColumn::make('subtitle')->label('字幕')->limit(30)->wrap(),
                Tables\Columns\TextColumn::make('ken_burns')->label('運鏡')->placeholder('繼承'),
                Tables\Columns\TextColumn::make('fit')->label('縮放'),
                Tables\Columns\TextColumn::make('transition')->label('轉場')
                    ->placeholder('繼承')
                    ->tooltip(fn (Shot $record) => '實際採用：' . $record->effectiveTransition()),
                Tables\Columns\TextColumn::make('voiceover_status')->label('配音')->badge(),
                Tables\Columns\TextColumn::make('video_status')->label('動畫')->badge(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('新增鏡頭')])
            ->actions([
                Tables\Actions\Action::make('pickImage')
                    ->label('挑選圖片')
                    ->icon('heroicon-o-photo')
                    ->form(fn () => [
                        Forms\Components\Select::make('product_image_id')->label('商品圖片')->required()
                            ->options($this->imageOptions())
                            ->helperText('綁定後會自動依長寬比帶出 contain / cover。'),
                    ])
                    ->action(function (Shot $record, array $data) {
                        $image = ProductImage::findOrFail($data['product_image_id']);

                        $record->update([
                            'product_image_id' => $image->id,
                            'image_url' => $image->local_path,
                            'image_remote_url' => $image->remote_url,
                            'fit' => $image->suggestedFit(),
                        ]);
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    /** @return array<string, string> */
    private function imageOptions(): array
    {
        return $this->getOwnerRecord()->images()
            ->get()
            ->mapWithKeys(fn (ProductImage $i) => [$i->id => "#{$i->sort_order}　{$i->width}x{$i->height}" . ($i->remote_url ? '' : '（未同步 S3）')])
            ->all();
    }
}
