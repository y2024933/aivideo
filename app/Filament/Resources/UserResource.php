<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = '帳號管理';

    protected static ?string $modelLabel = '帳號';

    protected static ?string $pluralModelLabel = '帳號';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('name')->label('姓名')->required()->maxLength(255),
                TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('password')->label('密碼')->password()->revealable()
                    ->required(fn (string $context): bool => $context === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $context) => $context === 'edit' ? '留空則不修改密碼，最少 6 位' : '最少 6 位')
                    ->minLength(6)->maxLength(255),
                Select::make('role')->label('角色')
                    ->options([
                        'super_admin' => '超級管理員',
                        'site_admin' => '站台管理員',
                    ])
                    ->required()
                    ->live()
                    ->afterStateHydrated(fn (Select $component, ?User $record) => $component->state($record?->getRoleNames()->first()))
                    ->dehydrated(false),
            ]),
            Select::make('sites')
                ->label('對應站台')
                ->relationship('sites', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->extraAttributes(['class' => 'line-target-select'])
                ->columnSpanFull()
                ->visible(fn (\Filament\Forms\Get $get) => $get('role') === 'site_admin'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('姓名')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->sortable(),
                TextColumn::make('roles.name')->label('角色')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'super_admin' => '超級管理員',
                        'site_admin' => '站台管理員',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'super_admin' => 'danger',
                        'site_admin' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('sites.name')->label('對應站台')
                    ->badge()->color('success')->limitList(3)
                    ->getStateUsing(fn ($record) => $record->hasRole('super_admin') ? ['全部站台'] : $record->sites->pluck('name')->toArray()),
                IconColumn::make('is_active')->label('啟用')->boolean(),
                TextColumn::make('last_login_at')->label('最後登入')->dateTime('Y-m-d H:i')->sortable()->placeholder('從未登入'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('啟用狀態'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
