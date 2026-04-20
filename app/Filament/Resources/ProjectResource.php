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

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('site_id')
                ->label('網站')
                ->relationship('site', 'name')
                ->required()
                ->visible(fn () => auth()->user()?->isSuperAdmin())
                ->default(fn () => auth()->user()?->sites()->value('sites.id'))
                ->reactive()
                ->afterStateUpdated(fn (callable $set) => $set('project_status_id', null)),
            TextInput::make('name')->label('建案名稱')->required()->maxLength(255),
            TextInput::make('slug')->label('頁面代碼')->required()->alphaDash(),
            Select::make('project_status_id')
                ->label('作品類型')
                ->required()
                ->options(function (callable $get) {
                    $siteId = $get('site_id') ?? auth()->user()?->sites()->value('sites.id');
                    if (! $siteId) return [];
                    return \App\Models\ProjectStatus::where('site_id', $siteId)
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all();
                }),
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
            TextInput::make('featured_image_alt')->label('圖片 Alt Text')->helperText('描述圖片內容，有助 SEO 與無障礙')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')->label('網站')->searchable(auth()->user()?->isSuperAdmin() ?? false)->visible(fn () => auth()->user()?->isSuperAdmin())->toggleable(),
                TextColumn::make('name')->label('建案名稱')->searchable()->sortable(),
                TextColumn::make('projectStatus.name')->label('作品類型')
                    ->badge(),
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
                Tables\Filters\SelectFilter::make('project_status_id')
                    ->label('作品類型')
                    ->relationship('projectStatus', 'name'),
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
