@props(['statePath' => null])

<x-filament-tiptap-editor::dropdown-button
    label="字級"
    icon="heading"
    :list="true"
>
    @foreach ([
        '12px' => '12px 小',
        '14px' => '14px',
        '16px' => '16px 預設',
        '18px' => '18px',
        '20px' => '20px',
        '24px' => '24px',
        '28px' => '28px',
        '32px' => '32px',
        '36px' => '36px',
        '48px' => '48px',
    ] as $size => $label)
        <li>
            <button
                type="button"
                x-on:click="editor().chain().focus().setMark('textStyle', { style: 'font-size: {{ $size }}' }).run(); $dispatch('close-panel')"
                class="w-full px-3 py-1.5 text-left hover:bg-gray-200 dark:hover:bg-gray-700"
                style="font-size: {{ $size }}"
            >
                {{ $label }}
            </button>
        </li>
    @endforeach
    <li>
        <button
            type="button"
            x-on:click="editor().chain().focus().unsetMark('textStyle').run(); $dispatch('close-panel')"
            class="w-full px-3 py-1.5 text-left hover:bg-gray-200 dark:hover:bg-gray-700 text-xs text-gray-500"
        >
            清除字級
        </button>
    </li>
</x-filament-tiptap-editor::dropdown-button>
