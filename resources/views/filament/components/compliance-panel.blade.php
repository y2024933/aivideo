{{--
    合規面板。顯示的是「上一次存檔的報告」（products.compliance_report）。
    checkpoint ② 的核准按鈕會重跑一次檢查並覆寫這份報告，所以 operator 改完字幕
    按下核准被擋時，這裡就會換成最新的違規清單。

    blocking 刻意「沒有」確認按鈕 —— 那是法規紅線，不是提醒。
--}}
@php
    use App\Filament\Resources\ProductResource;

    $record = $getRecord();
    $raw = $record?->compliance_report;
    $report = filled($raw['findings'] ?? null) || filled($raw['profile'] ?? null)
        ? App\Data\ComplianceReport::from($raw)
        : null;
    $staleRules = $report && $report->rulesFingerprint !== app(App\Services\Compliance\AdComplianceChecker::class)->rulesFingerprint();
    $simplified = $report
        ? array_values(array_filter($report->findings, fn ($f) => in_array($f->ruleId, ['zh_tw_simplified', 'zh_tw_glyph_variant'], true)))
        : [];
@endphp

@if (! $report)
    <p class="text-sm text-gray-500 dark:text-gray-400">尚未執行合規檢查。腳本生成後會自動掃描一次。</p>
@else
    <div class="space-y-4 text-sm">
        {{-- 檢查資訊 --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-gray-600 dark:text-gray-400">
            <span>合規類別：<span class="font-semibold">{{ $report->profile }}</span></span>
            <span>規則版本：{{ $report->rulesVersion }}（{{ $report->rulesFingerprint }}）</span>
            <span>檢查時間：{{ $report->checkedAt->format('Y-m-d H:i') }}</span>
            <span>
                結果：
                @if ($report->passed())
                    <span class="font-semibold text-success-600 dark:text-success-400">通過</span>
                @else
                    <span class="font-semibold text-danger-600 dark:text-danger-400">未通過</span>
                @endif
            </span>
        </div>

        @if ($report->profileBlocked)
            <div class="rounded-lg bg-danger-50 p-3 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
                ⛔ 此分類不開放製作影片：{{ $report->profileBlockedReason }}
            </div>
        @endif

        @if ($staleRules)
            <div class="rounded-lg bg-warning-50 p-3 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">
                ⚠️ 合規規則已更新（目前為 {{ app(App\Services\Compliance\AdComplianceChecker::class)->rulesFingerprint() }}），這份報告是用舊規則掃的，請重新核准以重新檢查。
            </div>
        @endif

        {{-- blocking：沒有確認按鈕 --}}
        <div>
            <h4 class="mb-2 font-semibold text-danger-600 dark:text-danger-400">
                ⛔ 必須修正（{{ count($report->blocking()) }}）
            </h4>

            @forelse ($report->blocking() as $finding)
                <div class="mb-2 rounded-lg border border-danger-200 bg-danger-50 p-3 dark:border-danger-400/30 dark:bg-danger-400/10">
                    <p class="font-medium text-danger-700 dark:text-danger-400">
                        {{ $finding->field }}：「{{ $finding->matched }}」{{ $finding->message }}
                    </p>
                    @if (filled($finding->law))
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">法規依據：{{ $finding->law }}</p>
                    @endif
                    @if (filled($finding->suggestion))
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">建議改法：{{ $finding->suggestion }}</p>
                    @endif
                </div>
            @empty
                <p class="text-gray-500 dark:text-gray-400">無。</p>
            @endforelse
        </div>

        {{-- warning：可逐條確認 --}}
        <div>
            <h4 class="mb-2 font-semibold text-warning-600 dark:text-warning-400">
                ⚠️ 需人工確認（{{ count($report->unacknowledgedWarnings()) }} / {{ count($report->warnings()) }}）
            </h4>

            @forelse ($report->warnings() as $finding)
                @php($low = $finding->confidence === 'low')
                <div @class([
                    'mb-2 flex items-start justify-between gap-3 rounded-lg border p-3',
                    'border-warning-200 bg-warning-50 dark:border-warning-400/30 dark:bg-warning-400/10' => ! $low && ! $finding->acknowledged,
                    'border-warning-100 bg-warning-50/50 dark:border-warning-400/10 dark:bg-warning-400/5' => $low && ! $finding->acknowledged,
                    'border-gray-200 bg-gray-50 opacity-75 dark:border-white/10 dark:bg-white/5' => $finding->acknowledged,
                ])>
                    <div>
                        <p @class(['font-medium', 'text-warning-700 dark:text-warning-400' => ! $finding->acknowledged, 'text-gray-500 dark:text-gray-400' => $finding->acknowledged])>
                            {{ $finding->field }}：「{{ $finding->matched }}」{{ $finding->message }}
                            @if ($low)
                                <span class="text-xs font-normal">（低信心，可能是誤判）</span>
                            @endif
                        </p>
                        @if (filled($finding->law))
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">法規依據：{{ $finding->law }}</p>
                        @endif
                        @if (filled($finding->suggestion))
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">建議改法：{{ $finding->suggestion }}</p>
                        @endif
                    </div>

                    @if ($finding->acknowledged)
                        <span class="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">✓ 已確認</span>
                    @else
                        <x-filament::button
                            size="xs"
                            color="warning"
                            wire:click="mountAction('acknowledgeFinding', @js(['key' => ProductResource::findingKey($finding)]))"
                            wire:loading.attr="disabled"
                        >
                            我已確認無誤
                        </x-filament::button>
                    @endif
                </div>
            @empty
                <p class="text-gray-500 dark:text-gray-400">無。</p>
            @endforelse
        </div>

        {{-- 簡體字與大陸字形：列出全部候選字，一簡對多繁不可自動轉換 --}}
        @if ($simplified !== [])
            <div>
                <h4 class="mb-2 font-semibold text-danger-600 dark:text-danger-400">簡體字／非台灣字形（{{ count($simplified) }}）</h4>
                <ul class="list-disc space-y-1 ps-5 text-gray-700 dark:text-gray-300">
                    @foreach ($simplified as $finding)
                        <li>
                            {{ $finding->field }} 第 {{ $finding->offset + 1 }} 字「{{ $finding->matched }}」
                            @if (count($finding->suggestions) > 1)
                                → 候選：{{ implode('、', $finding->suggestions) }}
                                <span class="text-xs text-gray-500 dark:text-gray-400">（一簡對多繁，請依語意自行選字）</span>
                            @elseif (filled($finding->suggestion))
                                → 「{{ $finding->suggestion }}」
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
