<div class="space-y-6">
    {{-- Header + filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Reporting') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Ad spend and lead volume, :from – :to', ['from' => $from, 'to' => $to]) }}</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
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
                {{ __('Run :cmd to load mock data.', ['cmd' => 'php artisan lodgely:import:ad-metrics --days=30']) }}
            </p>
        </div>
    @else
        {{-- KPI cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <x-kpi-card
                label="{{ __('Total spend') }}"
                value="{{ '$'.number_format($kpis['total_spend_cents'] / 100, 2) }}"
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
                <span class="font-semibold text-slate-800 dark:text-slate-200">${{ number_format($cpl, 2) }}</span>
            </p>
        @endif

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
                                    ? '$'.number_format($row->spend_cents / $row->platform_leads / 100, 2)
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
                                <td class="px-3 py-2.5 text-right text-slate-800 dark:text-slate-200 tabular-nums font-medium">${{ number_format($row->spend_cents / 100, 2) }}</td>
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
