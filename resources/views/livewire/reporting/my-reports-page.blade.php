<div class="space-y-6">
    {{-- Header + range filter --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('My reports') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                @auth
                    @if(auth()->user()->isOperator())
                        {{ __('All report views (operator preview).') }}
                    @else
                        {{ __('Your reporting views, configured by your account manager.') }}
                    @endif
                @endauth
            </p>
        </div>

        @if($selectedView)
            <div class="flex rounded-lg bg-slate-100 dark:bg-slate-800/80 p-0.5 text-xs font-medium gap-0.5">
                @foreach(['3' => __('3 months'), '6' => __('6 months'), '12' => __('12 months')] as $val => $label)
                    <button wire:click="$set('months', '{{ $val }}')"
                            class="px-3 py-1.5 rounded-md transition-colors {{ $months === $val ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @if($views->isEmpty())
        {{-- Empty state --}}
        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 px-6 py-12 text-center shadow-sm">
            <p class="text-slate-500 dark:text-slate-400 text-sm">{{ __('No reports have been configured for you yet.') }}</p>
            @if(auth()->user()->isOperator())
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                    {{ __('Create a view and assign it to a client in') }}
                    <a href="{{ route('reporting.views') }}" class="underline hover:text-slate-600 dark:hover:text-slate-300">{{ __('Report views') }}</a>.
                </p>
            @endif
        </div>
    @else
        {{-- View tabs --}}
        <div class="flex gap-1 border-b border-slate-200 dark:border-slate-700 overflow-x-auto">
            @foreach($views as $v)
                <button type="button"
                        wire:click="selectView('{{ $v->id }}')"
                        class="px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 -mb-px transition-colors
                            {{ $view === (string) $v->id
                                ? 'border-brand-500 text-brand-700 dark:text-brand-400'
                                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:border-slate-300 dark:hover:border-slate-600' }}">
                    {{ $v->name }}
                    @if(auth()->user()?->isOperator() && ! $v->is_live)
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-700/60 px-1.5 py-0.5 text-[10px] font-medium text-slate-500 dark:text-slate-400 align-middle">{{ __('Hidden') }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @if($selectedView)
            @if($aiSummary)
                <x-ai.summary-card :summary="$aiSummary" />
            @endif

            @if(config('lodgely.ai.enabled') && auth()->user()?->isOperator())
                <div class="flex justify-end">
                    <button type="button" wire:click="generateAiSummary"
                            class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        {{ __('Generate AI summary for this view') }}
                    </button>
                </div>
            @endif

            {{-- KPI summary strip --}}
            @if($columns && $rows->isNotEmpty())
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach($columns as $col)
                        <x-kpi-card
                            label="{{ $col->label() }}"
                            value="{{ $col->format($totals[$col->value] ?? null) }}"
                            tone="slate"
                        />
                    @endforeach
                </div>
            @endif

            {{-- Trend charts --}}
            @if($columns && $rows->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($columns as $col)
                        <x-reporting.metric-chart :column="$col" :rows="$rows" />
                    @endforeach
                </div>
            @endif

            {{-- Monthly table --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                            <tr>
                                <th class="px-3 py-2.5 text-left">{{ __('Month') }}</th>
                                @foreach($columns as $col)
                                    <th class="px-3 py-2.5 text-right">{{ $col->label() }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($rows as $row)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-200 tabular-nums">
                                        {{ \Carbon\Carbon::createFromFormat('Y-m', $row->month)->translatedFormat('M Y') }}
                                    </td>
                                    @foreach($columns as $col)
                                        <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums">
                                            {{ $col->format($row->{$col->value} ?? null) }}
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columns) + 1 }}" class="px-3 py-8 text-center text-slate-500 dark:text-slate-400">
                                        {{ __('No data for this period.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
