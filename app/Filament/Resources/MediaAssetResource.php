<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaAssetResource\Pages;
use App\Models\MediaAsset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = '媒體庫';

    protected static ?string $modelLabel = '媒體素材';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static ?string $pluralModelLabel = '媒體素材';

    protected static ?string $navigationGroup = '網站內容';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('site_id')
                ->label('網站')
                ->relationship('site', 'name')
                ->required()
                ->visible(fn () => auth()->user()?->isSuperAdmin())
                ->default(fn () => auth()->user()?->sites()->value('sites.id')),
            TextInput::make('name')
                ->label('媒體名稱')
                ->required()
                ->maxLength(255),
            Select::make('usage_type')
                ->label('用途')
                ->options([
                    'general' => '一般素材',
                    'hero' => '首頁 Banner',
                    'project' => '建案圖片',
                    'news' => '文章圖片',
                    'progress' => '工程進度',
                    'about' => '關於我們',
                ])
                ->default('general')
                ->required(),
            TextInput::make('folder')
                ->label('資料夾')
                ->default('media-library')
                ->maxLength(255),
            FileUpload::make('file_path')
                ->label('檔案')
                ->disk('public')
                ->directory(fn (callable $get) => $get('folder') ?: 'media-library')
                ->image()
                ->imageEditor()
                ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048))
                ->required(),
            TextInput::make('disk')
                ->label('儲存磁碟')
                ->default('public')
                ->disabled(),
            TextInput::make('alt_text')
                ->label('替代文字')
                ->maxLength(255),
            Textarea::make('caption')
                ->label('說明')
                ->rows(3)
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label('啟用')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                ImageColumn::make('file_path')
                    ->label('預覽')
                    ->disk('public')
                    ->square(),
                TextColumn::make('site.name')
                    ->label('網站')
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('名稱')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('usage_type')
                    ->label('用途')
                    ->badge(),
                TextColumn::make('folder')
                    ->label('資料夾')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('最後更新')
                    ->since(),
                IconColumn::make('is_active')
                    ->label('啟用')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('usage_type')
                    ->label('用途')
                    ->options([
                        'general' => '一般素材',
                        'hero' => '首頁 Banner',
                        'project' => '建案圖片',
                        'news' => '文章圖片',
                        'progress' => '工程進度',
                        'about' => '關於我們',
                    ]),
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
            'index' => Pages\ListMediaAssets::route('/'),
            'create' => Pages\CreateMediaAsset::route('/create'),
            'edit' => Pages\EditMediaAsset::route('/{record}/edit'),
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
