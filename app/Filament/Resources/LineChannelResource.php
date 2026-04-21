<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LineChannelResource\Pages;
use App\Models\LineChannel;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LineChannelResource extends Resource
{
    protected static ?string $model = LineChannel::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'LINE 頻道';

    protected static ?string $modelLabel = 'LINE 頻道';

    protected static ?string $pluralModelLabel = 'LINE 頻道';

    protected static ?string $navigationGroup = '網站管理';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('頻道設定')->schema([
                TextInput::make('name')->label('名稱')->required()->maxLength(255)
                    ->helperText('LINE Official Account 的名稱，方便辨識'),
                Textarea::make('channel_access_token')->label('Channel Access Token')->required()
                    ->rows(3)->helperText('從 LINE Developers Console 取得的 Long-lived Channel Access Token'),
                Textarea::make('channel_secret')->label('Channel Secret')->required()
                    ->rows(2)->helperText('從 LINE Developers Console 取得，用於驗證 Webhook 簽章'),
                TextInput::make('webhook_url')->label('Webhook URL')
                    ->disabled()->dehydrated(false)
                    ->helperText('請將此 URL 貼到 LINE Developers Console 的 Webhook URL 設定')
                    ->visible(fn ($record) => $record?->exists),
                Toggle::make('is_active')->label('啟用')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('名稱')->searchable(),
                TextColumn::make('webhook_url')->label('Webhook URL')->copyable()->limit(50)->getStateUsing(fn ($record) => $record->webhook_url),
                TextColumn::make('targets_count')->label('推播對象數')->counts('targets'),
                IconColumn::make('is_active')->label('啟用')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLineChannels::route('/'),
            'create' => Pages\CreateLineChannel::route('/create'),
            'edit' => Pages\EditLineChannel::route('/{record}/edit'),
        ];
    }
}
