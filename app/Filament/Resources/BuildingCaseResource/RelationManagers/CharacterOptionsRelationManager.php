<?php

namespace App\Filament\Resources\BuildingCaseResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CharacterOptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'characterOptions';

    protected static ?string $title = '角色選項';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('row_number')
                    ->label('#')
                    ->rowIndex(),
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('角色圖')
                    ->width(120)
                    ->height(120)
                    ->getStateUsing(fn ($record) => $record->image_url ? url($record->image_url) : null),
                Tables\Columns\TextColumn::make('prompt')->label('Prompt')->limit(50),
                Tables\Columns\TextColumn::make('status')->label('狀態')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'done' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_approved')
                    ->label('選用')
                    ->getStateUsing(fn ($record) => $record->id === $record->buildingCase?->approved_character_id)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('')
                    ->trueColor('success'),
                Tables\Columns\TextColumn::make('cost_usd')->label('費用')->money('USD'),
                Tables\Columns\TextColumn::make('created_at')->label('建立時間')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
