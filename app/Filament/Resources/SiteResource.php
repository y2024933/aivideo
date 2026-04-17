<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteResource\Pages;
use App\Models\Site;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('name')->label('網站名稱')->required()->maxLength(255),
                TextInput::make('brand_name')->label('品牌英文')->maxLength(255),
                TextInput::make('slug')->label('代碼')->required()->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('primary_domain')->label('主網域')->maxLength(255),
                TextInput::make('theme_key')->label('主題 Key')->required()->default('builder-classic'),
                TextInput::make('contact_email')->label('聯絡信箱')->email(),
                TextInput::make('primary_color')->label('主色'),
                TextInput::make('secondary_color')->label('輔色'),
                TextInput::make('contact_phone')->label('聯絡電話'),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->directory('site-logos')
                    ->image()
                    ->imageEditor()
                    ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
            ]),
            Grid::make(2)->schema([
                Toggle::make('is_active')->label('啟用站台')->default(true),
                Toggle::make('has_news')->label('啟用最新消息')->default(true),
                Toggle::make('has_projects')->label('啟用建案')->default(true),
                Toggle::make('has_progress')->label('啟用工程進度')->default(true),
                Toggle::make('has_contact_form')->label('啟用聯絡表單')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('網站')->searchable()->sortable(),
                TextColumn::make('primary_domain')->label('網域')->searchable(),
                TextColumn::make('theme_key')->label('主題'),
                IconColumn::make('is_active')->label('啟用')->boolean(),
            ])
            ->actions([
                Action::make('preview')
                    ->label('預覽')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Site $record): string => route('site.preview', $record))
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
        return [];
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
