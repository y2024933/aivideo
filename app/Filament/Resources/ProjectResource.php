<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = '建案管理';

    protected static ?string $modelLabel = '建案';

    protected static ?string $pluralModelLabel = '建案管理';

    protected static ?string $navigationGroup = '網站內容';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('site_id')
                ->label('網站')
                ->relationship('site', 'name')
                ->required()
                ->visible(fn () => auth()->user()?->isSuperAdmin())
                ->default(fn () => auth()->user()?->sites()->value('sites.id')),
            TextInput::make('name')->label('建案名稱')->required()->maxLength(255),
            TextInput::make('slug')->label('頁面代碼')->required()->alphaDash(),
            Select::make('status')->label('作品類型')->options([
                'selling' => '熱銷新案',
                'completed' => '歷史建案',
            ])->required(),
            Textarea::make('summary')->label('摘要'),
            TextInput::make('progress_password')->label('工程進度密碼')->password()->revealable()->helperText('設定後，訪客需輸入此密碼才能查看該建案的工程進度'),
            TextInput::make('location')->label('地點'),
            TextInput::make('address')->label('基地位置'),
            TextInput::make('launch_year')->label('年度')->numeric(),
            TextInput::make('area')->label('坪數'),
            TextInput::make('households')->label('規劃戶數'),
            TextInput::make('floors')->label('樓層規劃'),
            TextInput::make('layout_plan')->label('格局規劃'),
            FileUpload::make('featured_image_path')
                ->label('封面圖')
                ->disk('public')
                ->directory('project-images')
                ->image()
                ->imageEditor()
                ->maxSize((int) env('UPLOAD_MAX_SIZE_KB', 2048)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')->label('網站')->visible(fn () => auth()->user()?->isSuperAdmin())->toggleable(),
                TextColumn::make('name')->label('建案名稱')->searchable()->sortable(),
                TextColumn::make('status')->label('作品類型')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'selling' => '熱銷新案',
                        'completed' => '歷史建案',
                        default => $state,
                    }),
                TextColumn::make('location')->label('地點'),
                TextColumn::make('updated_at')->label('最後更新')->since(),
                Tables\Columns\ToggleColumn::make('is_featured')->label('首頁精選')
                    ->onColor('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('name')
                    ->label('建案名稱')
                    ->options(fn () => \App\Models\Project::query()
                        ->pluck('name', 'name')
                        ->toArray())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('作品類型')
                    ->options([
                        'selling' => '熱銷新案',
                        'completed' => '歷史建案',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->link(),
                Tables\Actions\DeleteAction::make()->link(),
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
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
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
