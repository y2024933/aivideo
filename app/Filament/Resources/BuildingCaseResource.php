<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BuildingCaseResource\Pages;
use App\Filament\Resources\BuildingCaseResource\RelationManagers;
use App\Models\BuildingCase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BuildingCaseResource extends Resource
{
    protected static ?string $model = BuildingCase::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?string $navigationLabel = '建案管理';

    protected static ?string $modelLabel = '建案';

    protected static ?string $pluralModelLabel = '建案';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('建案基本資料')->schema([
                    Forms\Components\TextInput::make('name')->label('建案名稱')->required(),
                    Forms\Components\TextInput::make('builder_name')->label('建商名稱'),
                    Forms\Components\TextInput::make('location')->label('地點'),
                    Forms\Components\TextInput::make('area_range')->label('坪數範圍'),
                    Forms\Components\TextInput::make('price_range')->label('價格範圍'),
                    Forms\Components\Select::make('target_audience')->label('目標客群')->options([
                        'first_buyer' => '首購族',
                        'upgrade' => '換屋族',
                        'retiree' => '退休族',
                        'investor' => '投資客',
                    ]),
                    Forms\Components\Select::make('tone')->label('調性')->options([
                        'warm_family' => '溫馨家庭',
                        'premium' => '質感',
                        'energetic' => '活力',
                        'luxury' => '豪宅',
                    ]),
                ])->columns(2),

                Forms\Components\Section::make('角色設定')->schema([
                    Forms\Components\TextInput::make('character_nickname')->label('角色暱稱'),
                    Forms\Components\Textarea::make('character_dna')->label('角色 DNA（英文 prompt）')->rows(4),
                ]),

                Forms\Components\Section::make('故事設定')->schema([
                    Forms\Components\Textarea::make('story_outline')->label('故事大綱')->rows(3),
                    Forms\Components\Textarea::make('must_have')->label('必要元素')->rows(2),
                    Forms\Components\Textarea::make('taboos')->label('禁忌')->rows(2),
                ])->columns(2),

                // 狀態 + 操作區（僅編輯時顯示）
                Forms\Components\Section::make('目前狀態')
                    ->schema([
                        Forms\Components\Placeholder::make('current_status')
                            ->label('狀態')
                            ->content(fn ($record) => $record?->status?->label() ?? '新建案'),
                        Forms\Components\Placeholder::make('current_cost')
                            ->label('累計費用')
                            ->content(fn ($record) => $record ? '$' . number_format((float) $record->cost_usd, 2) . ' USD' : '$0.00'),
                        Forms\Components\Placeholder::make('hint')
                            ->label('下一步')
                            ->content(fn ($record) => match ($record?->status) {
                                null, \App\Enums\CaseStatus::Draft => '儲存後，點下方「生成角色預覽」',
                                \App\Enums\CaseStatus::CharacterGenerating => '角色生成中，請稍後刷新頁面...',
                                \App\Enums\CaseStatus::CharacterPendingReview => '點下方「核准角色」選擇一張',
                                \App\Enums\CaseStatus::CharacterApproved => '點下方「生成場景圖」',
                                \App\Enums\CaseStatus::ImagesGenerating => '場景圖生成中...',
                                \App\Enums\CaseStatus::ImagesPendingReview => '點下方「核准場景圖，開始動畫」',
                                \App\Enums\CaseStatus::ProducingFinal => '動畫生成中，請稍後刷新...',
                                \App\Enums\CaseStatus::Completed => '影片製作完成！',
                                default => '請依下方按鈕操作',
                            }),
                    ])
                    ->columns(3)
                    ->hiddenOn('create'),

                // Shots Repeater（透過 relationship 自動同步）
                Forms\Components\Section::make('分鏡腳本')->schema([
                    Forms\Components\Repeater::make('shots')->relationship()->schema([
                        Forms\Components\TextInput::make('shot_id')->label('鏡頭 ID')->default('S01')->required(),
                        Forms\Components\TextInput::make('shot_order')->label('順序')->numeric()->default(1),
                        Forms\Components\TextInput::make('duration_seconds')->label('秒數')->numeric()->default(5),
                        Forms\Components\Textarea::make('scene_description')->label('場景描述')->rows(2),
                        Forms\Components\Textarea::make('voiceover_text')->label('旁白')->rows(2),
                        Forms\Components\Textarea::make('subtitle')->label('字幕'),
                        Forms\Components\TextInput::make('emotion')->label('情緒'),
                        Forms\Components\Textarea::make('flux_prompt')->label('Flux Prompt')->rows(3)->default(''),
                        Forms\Components\Textarea::make('kling_prompt')->label('Kling Prompt')->rows(2),
                    ])->columns(2)
                      ->defaultItems(0)
                      ->collapsible()
                      ->itemLabel(fn (array $state): string => $state['shot_id'] ?? 'Shot'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('建案名稱')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('builder_name')->label('建商'),
                Tables\Columns\TextColumn::make('status')->label('狀態')->badge(),
                Tables\Columns\TextColumn::make('character_nickname')->label('角色'),
                Tables\Columns\TextColumn::make('cost_usd')->label('費用')->money('USD'),
                Tables\Columns\TextColumn::make('shots_count')->label('鏡頭')->counts('shots'),
                Tables\Columns\TextColumn::make('created_at')->label('建立時間')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
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
        return [
            RelationManagers\ShotsRelationManager::class,
            RelationManagers\CharacterOptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBuildingCases::route('/'),
            'create' => Pages\CreateBuildingCase::route('/create'),
            'edit' => Pages\EditBuildingCase::route('/{record}/edit'),
        ];
    }

    // 排序由 table()->defaultSort() 處理
}
