<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 10px;" class="flex justify-center">
            <x-filament::button type="submit">
                儲存設定
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
