<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LineTargetResource\Pages;
use App\Filament\Resources\LineTargetResource\Widgets;
use App\Models\LineTarget;
use App\Models\Site;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LineTargetResource extends Resource
{
    protected static ?string $model = LineTarget::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'LINE 推播對象';

    protected static ?string $modelLabel = 'LINE 推播對象';

    protected static ?string $pluralModelLabel = 'LINE 推播對象';

    protected static ?string $navigationGroup = '網站管理';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('對象資訊')->schema([
                Select::make('line_channel_id')->label('LINE 頻道')->relationship('channel', 'name')->disabled(),
                Select::make('type')->label('類型')
                    ->options(['group' => '群組', 'user' => '個人'])->disabled(),
                TextInput::make('line_id')->label('LINE ID')->disabled(),
                TextInput::make('display_name')->label('顯示名稱'),
                Toggle::make('is_active')->label('啟用'),
            ]),
            Section::make('站台分配')->schema([
                CheckboxList::make('sites')
                    ->label('分配給以下站台')
                    ->relationship('sites', 'name')
                    ->helperText('勾選後，該站台的通知設定中就能選擇此推播對象')
                    ->columns(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('channel.name')->label('LINE 頻道'),
                TextColumn::make('type')->label('類型')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'group' => '群組',
                        'user' => '個人',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'group' => 'info',
                        'user' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('display_name')->label('顯示名稱')->searchable(),
                TextColumn::make('line_id')->label('LINE ID')->limit(20)->copyable(),
                TextColumn::make('sites.name')->label('分配站台')->badge()->separator(','),
                IconColumn::make('is_active')->label('啟用')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assignSites')
                        ->label('批量分配站台')
                        ->icon('heroicon-o-building-office-2')
                        ->form([
                            CheckboxList::make('site_ids')
                                ->label('分配給以下站台')
                                ->options(Site::pluck('name', 'id'))
                                ->columns(2)
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            foreach ($records as $target) {
                                $target->sites()->syncWithoutDetaching($data['site_ids']);
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('已批量分配站台'),
                    Tables\Actions\BulkAction::make('removeSites')
                        ->label('批量移除站台')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->form([
                            CheckboxList::make('site_ids')
                                ->label('從以下站台移除')
                                ->options(Site::pluck('name', 'id'))
                                ->columns(2)
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            foreach ($records as $target) {
                                $target->sites()->detach($data['site_ids']);
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('已批量移除站台'),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('批量啟用')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('已批量啟用'),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('批量停用')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('已批量停用'),
                ]),
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\LineTargetInfoWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLineTargets::route('/'),
            'edit' => Pages\EditLineTarget::route('/{record}/edit'),
        ];
    }
}
