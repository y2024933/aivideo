@php($user = auth()->user())

@if ($user)
    <div class="hidden sm:flex items-center gap-2 text-xs text-gray-500">
        @if ($user->isSuperAdmin())
            <span>權限：超級管理員</span>
            <span>·</span>
            <span>網站：全部站台</span>
        @else
            <span>權限：站台管理員</span>
            <span>·</span>
            <span>網站：{{ $user->sites->pluck('name')->join(' / ') }}</span>
        @endif
    </div>
@endif
