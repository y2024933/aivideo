{{-- EditRecord 的 $view 寫死在 vendor，無法覆寫；wire:poll 掛在 form 裡的 ViewField
     才會真的隨 EditProduct 這個 Livewire 元件一起輪詢（bare wire:poll = $refresh 整頁）。 --}}
@php($status = $getRecord()?->status)

@if ($status?->isProcessing())
    <div wire:poll.5s class="flex items-center gap-3 rounded-lg bg-primary-50 px-4 py-3 dark:bg-primary-400/10">
        <x-filament::loading-indicator class="h-5 w-5 text-primary-600 dark:text-primary-400" />
        <span class="text-sm font-medium text-primary-700 dark:text-primary-400">{{ $status->getLabel() }}，畫面每 5 秒自動更新…</span>
    </div>
@endif
