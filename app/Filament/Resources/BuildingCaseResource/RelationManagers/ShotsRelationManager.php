<?php

namespace App\Filament\Resources\BuildingCaseResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ShotsRelationManager extends RelationManager
{
    protected static string $relationship = 'shots';

    protected static ?string $title = '分鏡腳本';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shot_id')->label('鏡頭 ID')->sortable(),
                Tables\Columns\TextColumn::make('shot_order')->label('順序')->sortable(),
                Tables\Columns\TextColumn::make('duration_seconds')->label('秒數'),
                Tables\Columns\TextColumn::make('scene_description')->label('場景描述')->limit(30),
                Tables\Columns\ImageColumn::make('image_url')->label('場景圖'),
                Tables\Columns\TextColumn::make('image_status')->label('圖片狀態')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'info',
                        'done' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('video_status')->label('影片狀態')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'info',
                        'done' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subtitle')->label('字幕')->limit(30),
            ])
            ->defaultSort('shot_order')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
