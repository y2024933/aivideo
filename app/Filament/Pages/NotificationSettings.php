<?php

namespace App\Filament\Pages;

use App\Models\Site;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class NotificationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = '通知設定';

    protected static ?string $navigationGroup = '客戶管理';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = '通知設定';

    protected static string $view = 'filament.pages.notification-settings';

    public ?int $siteId = null;

    public ?array $data = [];

    public function mount(): void
    {
        $sites = $this->getSiteOptions();

        if (empty($sites)) {
            abort(403);
        }

        $this->siteId = array_key_first($sites);
        $this->fillForm();
    }

    public function getSiteOptions(): array
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return Site::pluck('name', 'id')->toArray();
        }

        return $user->sites()->pluck('sites.name', 'sites.id')->toArray();
    }

    public function fillForm(): void
    {
        $site = Site::find($this->siteId);
        if (! $site) return;

        $settings = $site->notification_settings ?? [];

        $this->form->fill([
            'site_id' => $this->siteId,
            'notify_enabled' => $settings['notify_enabled'] ?? false,
            'notify_emails' => $settings['notify_emails'] ?? [],
            'line_target_ids' => $site->lineTargets()->pluck('line_targets.id')->toArray(),
        ]);
    }

    public function switchSite(?string $state): void
    {
        $this->siteId = (int) $state;
        $this->fillForm();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('site_id')
                ->label('選擇站台')
                ->options(fn () => $this->getSiteOptions())
                ->live()
                ->afterStateUpdated(fn ($state) => $this->switchSite($state))
                ->visible(fn () => count($this->getSiteOptions()) > 1),
            Section::make('通知設定')->schema([
                Toggle::make('notify_enabled')
                    ->label('啟用表單通知')
                    ->helperText('啟用後，聯絡表單送出時會自動發送 Email 和 LINE 通知'),
                TagsInput::make('notify_emails')
                    ->label('通知 Email')
                    ->placeholder('輸入 Email 後按 Enter 新增')
                    ->helperText('收到聯絡表單時，通知這些 Email 地址')
                    ->splitKeys(['Enter', 'Tab', ','])
                    ->nestedRecursiveRules(['email'])
                    ->columnSpanFull(),
                Select::make('line_target_ids')
                    ->label('LINE 推播對象')
                    ->options(fn () => $this->getLineTargetOptions())
                    ->multiple()
                    ->searchable()
                    ->placeholder('選擇要接收通知的 LINE 群組或使用者')
                    ->helperText('選擇要接收通知的 LINE 群組或使用者')
                    ->extraAttributes([
                        'class' => 'line-target-select',
                    ])
                    ->columnSpanFull(),
            ]),
        ])->statePath('data');
    }

    public function getLineTargetOptions(): array
    {
        if (! $this->siteId) return [];

        $site = Site::find($this->siteId);
        if (! $site) return [];

        return $site->lineTargets()
            ->get(['line_targets.id', 'line_targets.display_name', 'line_targets.is_active'])
            ->mapWithKeys(fn ($t) => [
                $t->id => $t->is_active ? $t->display_name : "{$t->display_name}（已停用）",
            ])
            ->toArray();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // 權限驗證
        $user = auth()->user();
        $allowedSiteIds = $user->isSuperAdmin()
            ? Site::pluck('id')->toArray()
            : $user->sites()->pluck('sites.id')->toArray();

        if (! in_array($this->siteId, $allowedSiteIds)) {
            abort(403);
        }

        $site = Site::findOrFail($this->siteId);

        // 儲存 notification_settings JSON
        $site->update([
            'notification_settings' => [
                'notify_enabled' => $data['notify_enabled'] ?? false,
                'notify_emails' => $data['notify_emails'] ?? [],
            ],
        ]);

        // 安全 sync LINE targets（只允許已分配給該站台的）
        $allowedTargetIds = $site->lineTargets()->pluck('line_targets.id')->toArray();
        $safeIds = array_intersect($data['line_target_ids'] ?? [], $allowedTargetIds);
        $site->lineTargets()->sync($safeIds);

        Notification::make()->title('通知設定已儲存')->success()->send();
    }
}
