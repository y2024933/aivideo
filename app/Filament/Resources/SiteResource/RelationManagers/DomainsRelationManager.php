<?php

namespace App\Filament\Resources\SiteResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    protected static ?string $title = '網域管理';

    protected static ?string $modelLabel = '網域';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('domain')
                ->label('網域')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255)
                ->placeholder('example.com')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')->label('網域')->searchable(),
                TextColumn::make('created_at')->label('建立時間')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth('md')->modalFooterActionsAlignment('center'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->modalWidth('md')->modalFooterActionsAlignment('center'),
            ]);
    }
}
