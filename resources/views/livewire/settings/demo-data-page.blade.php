<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Demo data') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Load the canonical demo dataset into the inbox, or wipe it again when you are done evaluating. Real imports and webhook leads are not touched.') }}
        </p>
    </div>

    {{-- Status card --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                @if($status['loaded'])
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-3 py-1 text-xs font-medium text-emerald-800 dark:text-emerald-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ __('Demo data loaded') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-medium text-slate-600 dark:text-slate-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                        {{ __('No demo data') }}
                    </span>
                @endif
            </div>

            <dl class="flex items-center gap-6 text-xs text-slate-500 dark:text-slate-400">
                <div>
                    <dt class="uppercase tracking-wider">{{ __('Demo leads') }}</dt>
                    <dd class="mt-0.5 font-mono tabular-nums text-base text-slate-800 dark:text-slate-200">{{ $status['demo_leads'] }}</dd>
                </div>
                <div>
                    <dt class="uppercase tracking-wider">{{ __('Demo client users') }}</dt>
                    <dd class="mt-0.5 font-mono tabular-nums text-base text-slate-800 dark:text-slate-200">{{ $status['demo_users'] }}</dd>
                </div>
                @if($status['ad_metrics_removable'])
                    <div>
                        <dt class="uppercase tracking-wider">{{ __('Mock ad-metrics rows') }}</dt>
                        <dd class="mt-0.5 font-mono tabular-nums text-base text-slate-800 dark:text-slate-200">{{ $status['ad_metrics'] }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- What this includes --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-3 text-sm">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('What the demo dataset contains') }}</h2>
        <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-400">
            <li>{{ __('~60 neutral demo leads spread across sources, statuses and priorities') }}</li>
            <li>{{ __('12 Meta Lead Ads leads (6 for Northwind Studio, 6 for Acme Wellness) with realistic ad / adset / form attribution') }}</li>
            <li>{{ __('A pair of clear duplicates so the duplicate-detection UI is visible out of the box') }}</li>
            <li>{{ __('Two scoped client logins: client.northwind@example.com and client.acme@example.com (password: password)') }}</li>
            <li>{{ __('A demo operator login if it does not exist yet: operator@example.com (password: password)') }}</li>
        </ul>

        <p class="text-xs text-slate-500 dark:text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
            {{ __('All demo leads are attached to a tracking import labelled "Demo dataset", so unloading is a single scoped delete — your real imports stay intact. The currently signed-in user is never removed.') }}
            @if($status['ad_metrics_removable'])
                {{ __('Unloading also clears the mock ad-spend rows behind Reporting. (Skipped automatically once a live Meta or Google Ads connection exists, so real spend is never deleted — clear that from the Reporting page instead.)') }}
            @endif
        </p>
    </div>

    {{-- Actions --}}
    <div class="flex flex-wrap items-center gap-3">
        @if(!$status['loaded'])
            <button type="button" wire:click="load"
                    wire:loading.attr="disabled"
                    wire:target="load"
                    class="rounded-lg bg-slate-900 dark:bg-slate-100 px-4 py-2 text-sm font-medium text-white dark:text-slate-900 hover:opacity-90 transition-opacity disabled:opacity-60">
                <span wire:loading.remove wire:target="load">{{ __('Load demo data') }}</span>
                <span wire:loading wire:target="load">{{ __('Loading…') }}</span>
            </button>
        @else
            <button type="button" wire:click="unload"
                    wire:confirm="{{ __('Remove all demo leads, demo client users and mock ad-metrics rows? Your real imports and webhook leads will not be affected.') }}"
                    wire:loading.attr="disabled"
                    wire:target="unload"
                    class="rounded-lg border border-rose-300 dark:border-rose-700 bg-rose-50 dark:bg-rose-950/40 px-4 py-2 text-sm font-medium text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors disabled:opacity-60">
                <span wire:loading.remove wire:target="unload">{{ __('Unload demo data') }}</span>
                <span wire:loading wire:target="unload">{{ __('Removing…') }}</span>
            </button>

            <button type="button" wire:click="load"
                    wire:loading.attr="disabled"
                    wire:target="load"
                    class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-60">
                <span wire:loading.remove wire:target="load">{{ __('Reload (no-op if loaded)') }}</span>
                <span wire:loading wire:target="load">{{ __('Loading…') }}</span>
            </button>
        @endif
    </div>
</div>
