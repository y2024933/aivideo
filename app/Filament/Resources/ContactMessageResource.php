<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = '聯絡訊息';

    protected static ?string $modelLabel = '聯絡訊息';

    protected static ?string $pluralModelLabel = '聯絡訊息';

    protected static ?string $navigationGroup = '客戶管理';

    protected static ?int $navigationSort = 7;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('site_id')
                ->label('網站')
                ->relationship('site', 'name')
                ->disabled()
                ->visible(fn () => auth()->user()?->isSuperAdmin()),
            Select::make('project_id')->label('建案')->relationship('project', 'name')->disabled(),
            TextInput::make('inquiry_type')->label('詢問項目')->disabled(),
            TextInput::make('name')->label('姓名')->disabled(),
            TextInput::make('email')->label('Email')->disabled(),
            TextInput::make('phone')->label('電話')->disabled(),
            Textarea::make('message')->label('留言')->rows(4)->disabled(),
            Select::make('status')->label('處理狀態')->options([
                'new' => '新進',
                'processing' => '處理中',
                'closed' => '已結案',
            ])->required(),
            TextInput::make('assigned_to')->label('負責人'),
            DateTimePicker::make('processed_at')->label('處理時間'),
            Textarea::make('notes')->label('內部備註')->rows(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')->label('網站')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
                TextColumn::make('name')->label('姓名')->searchable(),
                TextColumn::make('phone')->label('電話'),
                TextColumn::make('inquiry_type')->label('詢問項目'),
                TextColumn::make('status')->label('狀態')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'new' => '新進',
                        'processing' => '處理中',
                        'closed' => '已結案',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'info',
                        'processing' => 'warning',
                        'closed' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('assigned_to')->label('負責人'),
                TextColumn::make('created_at')->label('建立時間')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('site_id')
                    ->label('網站')
                    ->relationship('site', 'name')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('狀態')
                    ->options([
                        'new' => '新進',
                        'processing' => '處理中',
                        'closed' => '已結案',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('處理'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
            'edit' => Pages\EditContactMessage::route('/{record}/edit'),
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
