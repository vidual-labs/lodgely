<div class="space-y-6">
    @if(session('status'))
        <div class="rounded-lg border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-2.5 text-sm text-emerald-800 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    {{-- Header + filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Reporting') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Ad spend and lead volume, :from – :to', ['from' => $from, 'to' => $to]) }}</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- Fetch the latest metrics on demand instead of waiting for the daily
                 05:00 scheduled run. Native POST → controller → redirect, per the
                 Livewire-morph rails — the call is synchronous (a few seconds). --}}
            <form method="POST" action="{{ route('reporting.ad-metrics.fetch') }}"
                  onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = '{{ __('Fetching…') }}';">
                @csrf
                <button type="submit"
                        class="rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-60 shadow-sm">
                    {{ __('Fetch data now') }}
                </button>
            </form>

            {{-- Clear ad-metrics data (demo / mock spend lives in ad_spend_reports
                 with no per-import tag, so this is the only way to wipe it). Native
                 POST form → controller → redirect, per the Livewire-morph rails. --}}
            @if($kpis['has_data'])
                <form method="POST" action="{{ route('reporting.ad-metrics.purge') }}"
                      onsubmit="return confirm('{{ __('Delete all ad-metrics data (spend, clicks, impressions, platform leads) for every campaign? Your leads are not affected. Mock sources will repopulate on the next import run.') }}');">
                    @csrf
                    <button type="submit"
                            class="rounded-lg border border-rose-300 dark:border-rose-700 bg-rose-50 dark:bg-rose-950/40 px-3 py-1.5 text-xs font-medium text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors">
                        {{ __('Clear ad-metrics data') }}
                    </button>
                </form>
            @endif

            {{-- Platform filter --}}
            <div class="flex rounded-lg bg-slate-100 dark:bg-slate-800/80 p-0.5 text-xs font-medium gap-0.5">
                @foreach(['all' => __('All'), 'meta' => 'Meta', 'google' => 'Google'] as $val => $label)
                    <button wire:click="$set('platform', '{{ $val }}')"
                            class="px-3 py-1.5 rounded-md transition-colors {{ $platform === $val ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Date range filter --}}
            <div class="flex rounded-lg bg-slate-100 dark:bg-slate-800/80 p-0.5 text-xs font-medium gap-0.5">
                @foreach(['7' => __('7 days'), '30' => __('30 days'), '90' => __('90 days')] as $val => $label)
                    <button wire:click="$set('range', '{{ $val }}')"
                            class="px-3 py-1.5 rounded-md transition-colors {{ $range === $val ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @if(!$kpis['has_data'])
        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 px-6 py-12 text-center shadow-sm">
            <p class="text-slate-500 dark:text-slate-400 text-sm">{{ __('No ad metrics yet for this period.') }}</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                {{ __('Metrics are pulled automatically once a day. Click "Fetch data now" above to load the latest figures right away.') }}
            </p>
            <form method="POST" action="{{ route('reporting.ad-metrics.fetch') }}" class="mt-4"
                  onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = '{{ __('Fetching…') }}';">
                @csrf
                <button type="submit"
                        class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-60 shadow-sm">
                    {{ __('Fetch data now') }}
                </button>
            </form>
        </div>
    @else
        {{-- KPI cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <x-kpi-card
                label="{{ __('Total spend') }}"
                value="{{ \App\Support\Money::fromCents($kpis['total_spend_cents'], $kpis['currency']) }}"
                tone="blue"
            />
            <x-kpi-card
                label="{{ __('Clicks') }}"
                value="{{ number_format($kpis['total_clicks']) }}"
                tone="slate"
            />
            <x-kpi-card
                label="{{ __('Impressions') }}"
                value="{{ number_format($kpis['total_impressions']) }}"
                tone="slate"
            />
            <x-kpi-card
                label="{{ __('Platform leads') }}"
                value="{{ number_format($kpis['total_platform_leads']) }}"
                tone="emerald"
            />
            <x-kpi-card
                label="{{ __('Lodgely leads') }}"
                value="{{ number_format($kpis['total_lodgely_leads']) }}"
                tone="amber"
            />
        </div>

        @php
            $cpl = $kpis['total_platform_leads'] > 0
                ? $kpis['total_spend_cents'] / $kpis['total_platform_leads'] / 100
                : null;
        @endphp

        @if($cpl !== null)
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Cost per lead (platform):') }}
                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ \App\Support\Money::amount($cpl, $kpis['currency']) }}</span>
            </p>
        @endif

        {{-- Trend charts (daily time series over the selected range) --}}
        @php
            $fmtDay = fn ($d) => \Carbon\Carbon::parse($d)->translatedFormat('j M');

            $trendCharts = [
                ['title' => __('Total spend'),    'tone' => 'blue',    'key' => 'spend_cents',    'money' => true],
                ['title' => __('Clicks'),         'tone' => 'slate',   'key' => 'clicks',         'money' => false],
                ['title' => __('Impressions'),    'tone' => 'slate',   'key' => 'impressions',    'money' => false],
                ['title' => __('Platform leads'), 'tone' => 'emerald', 'key' => 'platform_leads', 'money' => false],
                ['title' => __('Lodgely leads'),  'tone' => 'amber',   'key' => 'lodgely_leads',  'money' => false],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($trendCharts as $chart)
                @php
                    $points = $series->map(function ($row) use ($chart, $fmtDay) {
                        $value = $row->{$chart['key']};

                        return [
                            'label'   => $fmtDay($row->date),
                            'value'   => (float) ($chart['money'] ? $value / 100 : $value),
                            'display' => $chart['money']
                                ? \App\Support\Money::fromCents($value, $kpis['currency'])
                                : number_format($value),
                        ];
                    })->all();
                @endphp
                <x-reporting.trend-chart
                    :title="$chart['title']"
                    :points="$points"
                    :tone="$chart['tone']"
                />
            @endforeach
        </div>

        {{-- Campaign breakdown --}}
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mb-2">{{ __('By campaign') }}</h2>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2.5 text-left">{{ __('Platform') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ __('Campaign') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ __('Impressions') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ __('Clicks') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ __('Spend') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ __('CPL') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ __('Platform leads') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ __('Lodgely leads') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($campaigns as $row)
                            @php
                                $rowCpl = $row->platform_leads > 0
                                    ? \App\Support\Money::amount($row->spend_cents / $row->platform_leads / 100, $row->currency)
                                    : '—';
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                                        {{ $row->platform === 'meta' ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                        {{ ucfirst($row->platform) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-slate-800 dark:text-slate-200 max-w-xs truncate">
                                    {{ $row->campaign_name ?? $row->campaign_id }}
                                </td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums">{{ number_format($row->impressions) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums">{{ number_format($row->clicks) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-800 dark:text-slate-200 tabular-nums font-medium">{{ \App\Support\Money::fromCents($row->spend_cents, $row->currency) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums">{{ $rowCpl }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums">{{ number_format($row->platform_leads) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums">{{ number_format($row->lodgely_leads) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">{{ __('No campaigns found for this period.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Lead source breakdown (always shown, from lodgely's own data) --}}
    @if($bySource->isNotEmpty())
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mb-2">{{ __('Leads by source') }}</h2>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2.5 text-left">{{ __('Source') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ __('Leads') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($bySource as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-3 py-2.5 text-slate-800 dark:text-slate-200">{{ $row->source }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-400 tabular-nums">{{ number_format($row->lead_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
