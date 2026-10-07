<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Data\ComplianceReport;
use App\Enums\ProductStatus;
use App\Filament\Resources\ProductResource;
use App\Jobs\GenerateScriptJob;
use App\Models\Product;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public function getHeading(): string
    {
        return "編輯商品：{$this->record->title}（{$this->record->status->getLabel()}）";
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()->label('刪除')];
    }

    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            $this->submitProductAction(),
            $this->approveProductAction(),
            $this->generateScriptAction(),
            $this->approveScriptAction(),
            $this->acknowledgeFindingAction(),
            $this->archiveAction(),
        ];
    }

    /** 草稿送出人工確認（checkpoint ① 的入口） */
    private function submitProductAction(): Actions\Action
    {
        return Actions\Action::make('submitProduct')
            ->label('送出商品資料待確認')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn () => $this->record->status === ProductStatus::Draft)
            ->action(function () {
                $this->save(shouldRedirect: false);
                $this->record->transitionTo(ProductStatus::ProductPendingReview, 'operator');
                Notification::make()->success()->title('已送出，待確認')->send();
            });
    }

    /** checkpoint ①：商品資料核准 */
    private function approveProductAction(): Actions\Action
    {
        $blockers = ProductResource::approvalBlockers($this->record);

        return Actions\Action::make('approveProduct')
            ->label('① 核准商品資料')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn () => $this->record->status === ProductStatus::ProductPendingReview)
            ->disabled($blockers !== [])
            ->tooltip($blockers === [] ? null : '尚缺：' . implode('、', $blockers))
            ->action(function () {
                $this->record->transitionTo(ProductStatus::ProductApproved, 'operator');
                Notification::make()->success()->title('商品資料已確認')->body('下一步：生成腳本（P3）。')->send();
            });
    }

    /** 丟 LLM 寫稿（P3）。狀態轉移交給 Job，按鈕只負責派工。 */
    private function generateScriptAction(): Actions\Action
    {
        return Actions\Action::make('generateScript')
            ->label('生成腳本')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('會呼叫 LLM 寫稿並重建所有鏡頭，現有鏡頭與字幕會被覆蓋。')
            ->visible(fn () => in_array($this->record->status, [ProductStatus::ProductApproved, ProductStatus::ScriptFailed], true))
            ->action(function () {
                GenerateScriptJob::dispatch($this->record->id);
                Notification::make()->success()->title('已排入腳本生成')->body('完成後狀態會變成「② 腳本待審核」。')->send();
            });
    }

    /**
     * checkpoint ②：腳本核准。
     *
     * ⚠️ gate 一定要「重跑」合規檢查。operator 在這一頁可以直接改字幕與貼文，
     * 只看 products.compliance_report 等於核准的是 LLM 當初寫的字，不是現在要上線的字。
     */
    private function approveScriptAction(): Actions\Action
    {
        return Actions\Action::make('approveScript')
            ->label('② 核准腳本')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn () => $this->record->status === ProductStatus::ScriptPendingReview)
            ->action(function () {
                $report = ProductResource::checkScript($this->record);
                ProductResource::storeComplianceReport($this->record, $report);
                $blockers = ProductResource::scriptApprovalBlockers($this->record->refresh(), $report);

                if ($blockers !== []) {
                    Notification::make()->danger()->title('不可核准')->body(implode('；', $blockers))->persistent()->send();

                    return;
                }

                $this->record->transitionTo(ProductStatus::ScriptApproved, 'operator');
                Notification::make()->success()->title('腳本已核准')->body('下一步：產生素材（P5）。')->send();
            });
    }

    /**
     * 逐條確認 warning（由合規面板的按鈕帶 key 進來）。
     *
     * blocking 永遠不在可確認清單裡：它是法規紅線，沒有「我確認無誤」的空間。
     */
    private function acknowledgeFindingAction(): Actions\Action
    {
        return Actions\Action::make('acknowledgeFinding')
            ->label('確認此警告')
            // 這顆按鈕只從合規面板的 wire:click="mountAction(...)" 帶 key 進來，
            // 不該出現在表單底部的按鈕列（沒有 key 的話按了也沒意義），但仍必須註冊才掛得上。
            ->extraAttributes(['class' => 'hidden'])
            ->requiresConfirmation()
            ->modalHeading('確認此警告無誤')
            ->modalDescription('確認後這一項就不再卡住核准。請確定文案內容真的站得住腳 —— 違規的是你。')
            ->action(function (array $arguments) {
                $key = (string) ($arguments['key'] ?? '');
                $raw = (array) ($this->record->compliance_report ?? []);
                $matched = false;

                foreach ((array) ($raw['findings'] ?? []) as $index => $finding) {
                    if (($finding['severity'] ?? null) === 'warning' && ProductResource::findingKey((array) $finding) === $key) {
                        $raw['findings'][$index]['acknowledged'] = true;
                        $matched = true;
                    }
                }

                if (! $matched) {
                    Notification::make()->warning()->title('找不到這一項警告')->body('報告可能已經重新產生，請重新整理頁面。')->send();

                    return;
                }

                $report = ComplianceReport::from($raw);
                $this->record->update([
                    'compliance_report' => $report->toArray(),
                    'compliance_passed' => $report->passed(),
                ]);

                Notification::make()->success()->title('已確認')
                    ->body('尚待確認：' . count($report->unacknowledgedWarnings()) . ' 項')->send();
            });
    }

    private function archiveAction(): Actions\Action
    {
        return Actions\Action::make('archive')
            ->label('封存')
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn () => $this->record->status !== ProductStatus::Archived)
            ->action(function () {
                $this->record->transitionTo(ProductStatus::Archived, 'operator');
                Notification::make()->success()->title('已封存')->send();
            });
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['subtitle_settings'] ??= config('video.subtitle_defaults');

        return ProductResource::adoptBgmUpload($data);
    }

    /** @return Product */
    public function getRecord(): Product
    {
        return parent::getRecord();
    }
}
