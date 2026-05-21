<div class="space-y-6">
    {{-- ────────────────── header + KPI toggle ───────────────── --}}
    <div x-data="{ kpiOpen: false }">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Lead inbox') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @auth
                        @if(auth()->user()->isClient())
                            {{ __('Your leads across all configured sources.') }}
                        @else
                            {{ __('All leads across all sources for this workspace.') }}
                        @endif
                    @endauth
                </p>
            </div>

            <div class="flex items-center gap-3">
                {{-- KPI toggle --}}
                <button type="button" @click="kpiOpen = !kpiOpen"
                        class="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors flex items-center gap-1">
                    <span x-text="kpiOpen ? '{{ __('Hide stats') }}' : '{{ __('Show stats') }}'"></span>
                    <svg :class="kpiOpen ? 'rotate-180' : ''"
                         class="w-3 h-3 transition-transform"
                         viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 4l4 4 4-4"/>
                    </svg>
                </button>

                @auth
                    @if(auth()->user()->isOperator())
                        <div class="flex items-center gap-2" wire:ignore.self>
                            <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                                <a href="{{ route('inbox.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                                   class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                    {{ __('Export CSV') }}
                                </a>
                                <a href="{{ route('inbox.export', array_merge(request()->query(), ['format' => 'ndjson'])) }}"
                                   class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border-l border-slate-200 dark:border-slate-700">
                                    {{ __('Export JSON') }}
                                </a>
                            </div>
                            <button type="button" wire:click="openManualForm"
                                    class="inline-flex items-center gap-2 rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                                + {{ __('New lead') }}
                            </button>
                        </div>
                    @endif
                @endauth
            </div>
        </div>

        {{-- KPI strip — hidden by default --}}
        <div x-show="kpiOpen" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <x-kpi-card :label="__('New')"        :value="$kpis['new']"        tone="blue"  />
            <x-kpi-card :label="__('Duplicates')" :value="$kpis['duplicates']" tone="rose"  />
            <x-kpi-card :label="__('Incomplete')" :value="$kpis['incomplete']" tone="amber" />
            <x-kpi-card :label="__('Total')"      :value="$kpis['total']"      tone="slate" />
        </div>
    </div>

    {{-- ────────────────── filter bar + sources ───────────────── --}}
    <div x-data="{ sourcesOpen: false, savedOpen: false, columnsOpen: false }" class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-3 shadow-sm">

        {{-- ── toolbar row ── --}}
        @php
            $activeFilterCount = (int)($search !== '') + (int)($status !== '')
                + (int)($priority !== '') + (int)($source !== '') + (int)($client !== '');
        @endphp
        <div class="flex flex-wrap items-center gap-2">

            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search name, email, phone, message…') }}"
                   class="min-w-[160px] grow rounded-lg border-slate-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 px-2.5">

            <select wire:model.live="status"
                    class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 pl-2.5 pr-7">
                <option value="">{{ __('Status') }}</option>
                @foreach($statusOptions as $o)
                    <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                @endforeach
            </select>

            <select wire:model.live="priority"
                    class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 pl-2.5 pr-7">
                <option value="">{{ __('Priority') }}</option>
                @foreach($priorityOptions as $o)
                    <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                @endforeach
            </select>

            <select wire:model.live="source"
                    class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 pl-2.5 pr-7">
                <option value="">{{ __('Source') }}</option>
                @foreach($sourceOptions as $o)
                    <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                @endforeach
            </select>

            @auth
                @if(auth()->user()->isOperator())
                    <select wire:model.live="client"
                            class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 pl-2.5 pr-7">
                        <option value="">{{ __('Client') }}</option>
                        @foreach($clientOptions as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                @endif
            @endauth

            <select wire:model.live="sort"
                    class="rounded-lg border-slate-300 text-xs focus:border-brand-500 focus:ring-brand-500 h-8 py-0 pl-2.5 pr-7 text-slate-600 dark:text-slate-400">
                <option value="created_desc">{{ __('Newest first') }}</option>
                <option value="created_asc">{{ __('Oldest first') }}</option>
                <option value="priority_desc">{{ __('Priority ↓') }}</option>
            </select>

            {{-- active-filter count badge --}}
            @if($activeFilterCount > 0)
                <span class="inline-flex items-center rounded-full bg-brand-100 dark:bg-brand-900/40 px-2 py-0.5 text-xs font-medium text-brand-600 dark:text-brand-400 ring-1 ring-brand-200 dark:ring-brand-900/60">
                    {{ $activeFilterCount }}
                </span>
            @endif

            {{-- right-side: lead count · Show group · actions. On <sm we drop --}}
            {{-- shrink-0 / ml-auto / justify-end and force w-full so the group --}}
            {{-- wraps to its own row(s) under the filters instead of overflowing. --}}
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 w-full sm:w-auto sm:ml-auto sm:justify-end text-xs text-slate-500 dark:text-slate-400">
                {{-- lead count --}}
                <span class="tabular-nums text-slate-400 dark:text-slate-500 select-none">
                    {{ number_format($leads->total()) }}&thinsp;{{ trans_choice('lead|leads', $leads->total()) }}
                </span>

                <span class="text-slate-200 dark:text-slate-700 select-none px-0.5 hidden sm:inline">|</span>

                {{-- Show: group --}}
                <span class="text-slate-400 dark:text-slate-500 select-none">{{ __('Show:') }}</span>
                @if($kpis['by_source']->isNotEmpty())
                    <button type="button" @click="sourcesOpen = !sourcesOpen"
                            :class="sourcesOpen ? 'text-slate-800 dark:text-slate-100 font-medium' : ''"
                            class="hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Sources') }}</button>
                    <span class="text-slate-200 dark:text-slate-700 select-none hidden sm:inline">·</span>
                @endif
                @if($savedFilters->isNotEmpty())
                    <button type="button" @click="savedOpen = !savedOpen"
                            :class="savedOpen ? 'text-slate-800 dark:text-slate-100 font-medium' : ''"
                            class="hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Saved views') }}</button>
                    <span class="text-slate-200 dark:text-slate-700 select-none hidden sm:inline">·</span>
                @endif
                {{-- Custom columns toggle — opens an inline expansion row below --}}
                {{-- the toolbar (same pattern as Sources / Saved views). Open --}}
                {{-- state lives in the parent x-data so it survives Livewire --}}
                {{-- morphs from chip clicks — no dropdown, no fixed position, --}}
                {{-- no wire:ignore gymnastics. --}}
                <button type="button" @click="columnsOpen = !columnsOpen"
                        :class="columnsOpen ? 'text-slate-800 dark:text-slate-100 font-medium' : ''"
                        class="hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Custom columns') }}</button>

                <span class="text-slate-200 dark:text-slate-700 select-none px-0.5 hidden sm:inline">|</span>

                {{-- Save current view dropdown --}}
                <div x-data="{ open: false }"
                     wire:ignore.self
                     @keydown.escape.window="open = false"
                     @inbox-saved-filter-stored.window="open = false"
                     class="relative">
                    <button type="button" @click="open = !open; if (open) $wire.openSaveDialog()"
                            :class="open ? 'text-slate-800 dark:text-slate-100 font-medium' : ''"
                            class="hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                        {{ __('Save current view') }}
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute right-0 top-full mt-2 z-30 w-[min(320px,90vw)] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg p-3 space-y-2 text-left">
                        <label class="block text-xs text-slate-600 dark:text-slate-400">{{ __('Name this view') }}</label>
                        <input type="text" wire:model="newFilterName" placeholder="{{ __('e.g. New leads this week') }}"
                               maxlength="100" autocomplete="off"
                               @keydown.enter.prevent="$wire.saveFilter()"
                               class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('newFilterName')
                            <span class="block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                        <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                            <input type="checkbox" wire:model="newFilterIsDefault"
                                   class="rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                            {{ __('Set as my default view') }}
                        </label>
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button type="button" @click="$wire.closeSaveDialog(); open = false"
                                    class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">{{ __('Cancel') }}</button>
                            <button type="button" wire:click="saveFilter"
                                    class="rounded-lg bg-slate-900 dark:bg-slate-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">
                                {{ __('Save') }}
                            </button>
                        </div>
                    </div>
                </div>

                <button type="button" wire:click="clearFilters"
                        @class([
                            'transition-colors',
                            'text-brand-600 dark:text-brand-400 font-medium hover:text-brand-500 dark:hover:text-brand-200' => $activeFilterCount > 0,
                            'text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200' => $activeFilterCount === 0,
                        ])>{{ __('Clear') }}</button>
            </div>
        </div>

        {{-- ── sources panel ── --}}
        @if($kpis['by_source']->isNotEmpty())
            <div x-show="sourcesOpen" x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-800 flex flex-wrap gap-1.5">
                @foreach($kpis['by_source'] as $row)
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-400">
                        {{ $row->source }}
                        <span class="font-normal text-slate-400 dark:text-slate-500">{{ $row->total }}</span>
                    </span>
                @endforeach
            </div>
        @endif

        {{-- ── custom columns picker ── --}}
        {{-- Inline expansion row, same shape as the Sources / Saved-views --}}
        {{-- panels. State lives in the parent x-data (columnsOpen) so chip --}}
        {{-- clicks (which trigger a Livewire morph) can't reset the open --}}
        {{-- state. Every chip toggle auto-saves to users.inbox_columns — no --}}
        {{-- separate "Done" step. --}}
        <div x-show="columnsOpen" x-cloak
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-800 space-y-3">
            @php
                $picked = $activeColumns;
                $pickedQs = $activeQuestions;
                $total = count($picked) + count($pickedQs);
                $colLabelsPicker = [
                    'received' => __('Received'),
                    'name' => __('Name'), 'email' => __('Email'), 'phone' => __('Phone'),
                    'client' => __('Client'), 'source' => __('Source'),
                    'campaign' => __('Campaign'), 'form' => __('Form'), 'platform' => __('Platform'),
                    'status' => __('Status'), 'priority' => __('Priority'), 'outreach' => __('Outreach'),
                ];
            @endphp
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="text-xs text-slate-600 dark:text-slate-400">
                    {{ __('Visible columns') }}
                    <span class="ml-1 text-slate-400 dark:text-slate-500">
                        ({{ $total }} / {{ \App\Livewire\Inbox\InboxPage::MAX_TOTAL_COLUMNS }})
                    </span>
                </div>
                <button type="button" wire:click="resetColumnPicker"
                        class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Reset to defaults') }}</button>
            </div>

            <div class="flex flex-wrap gap-1.5">
                @foreach(\App\Livewire\Inbox\InboxPage::AVAILABLE_COLUMNS as $key)
                    @php $isOn = in_array($key, $picked, true); @endphp
                    <button type="button"
                            wire:key="col-chip-{{ $key }}"
                            wire:click="togglePickedColumn('{{ $key }}')"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-colors',
                                'bg-slate-900 text-white ring-slate-900 dark:bg-slate-200 dark:text-slate-900 dark:ring-slate-200' => $isOn,
                                'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 ring-slate-300/50 dark:ring-slate-600/50 hover:bg-slate-100 dark:hover:bg-slate-800' => !$isOn,
                            ])>
                        <span aria-hidden="true">{{ $isOn ? '✓' : '+' }}</span>
                        <span>{{ $colLabelsPicker[$key] ?? $key }}</span>
                    </button>
                @endforeach
            </div>

            @if(!empty($availableQuestions))
                <div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 mb-1.5">
                        {{ __('Custom form questions') }}
                        <span class="ml-1 text-slate-400 dark:text-slate-500">
                            ({{ count($pickedQs) }} / {{ \App\Livewire\Inbox\InboxPage::MAX_QUESTION_COLUMNS }})
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($availableQuestions as $q)
                            @php $isOn = in_array($q, $pickedQs, true); @endphp
                            <button type="button"
                                    wire:key="q-chip-{{ md5($q) }}"
                                    wire:click="togglePickedQuestion(@js($q))"
                                    title="{{ $q }}"
                                    @class([
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-colors',
                                        'bg-indigo-600 text-white ring-indigo-600 dark:bg-indigo-500 dark:ring-indigo-500' => $isOn,
                                        'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 ring-slate-300/50 dark:ring-slate-600/50 hover:bg-slate-100 dark:hover:bg-slate-800' => !$isOn,
                                    ])>
                                <span aria-hidden="true">{{ $isOn ? '✓' : '+' }}</span>
                                <span>{{ \Illuminate\Support\Str::limit($q, 32) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                    {{ __('No custom-question columns available — leads with form answers will populate this list automatically.') }}
                </p>
            @endif
        </div>

        {{-- ── saved filter chips ── --}}
        @if($savedFilters->isNotEmpty())
            <div x-show="savedOpen" x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center gap-2">
                @foreach($savedFilters as $sf)
                    <span wire:key="sf-{{ $sf->id }}"
                          class="inline-flex items-center rounded-full border pl-2.5 pr-1 py-0.5 text-xs gap-1
                                 {{ $sf->is_default
                                     ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/50'
                                     : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800' }}">
                        <button type="button" wire:click="loadFilter({{ $sf->id }})"
                                class="font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100 max-w-[140px] truncate transition-colors"
                                title="{{ $sf->name }}">
                            {{ $sf->name }}
                        </button>
                        <button type="button" wire:click="toggleDefaultFilter({{ $sf->id }})"
                                aria-label="{{ $sf->is_default ? __('Remove as default view') : __('Set as default view') }}"
                                title="{{ $sf->is_default ? __('Remove as default view') : __('Set as default view') }}"
                                class="{{ $sf->is_default ? 'text-amber-500 hover:text-amber-600' : 'text-slate-300 dark:text-slate-600 hover:text-amber-400' }} leading-none px-0.5">
                            ★
                        </button>
                        <button type="button" wire:click="deleteFilter({{ $sf->id }})"
                                aria-label="{{ __('Delete') }} {{ $sf->name }}"
                                title="{{ __('Delete') }}"
                                class="text-slate-300 dark:text-slate-600 hover:text-red-500 leading-none px-0.5">
                            ×
                        </button>
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ────────────────── bulk action bar ───────────────── --}}
    @auth
        @if(auth()->user()->isOperator() && count($bulkSelected) > 0)
            <div class="rounded-xl border border-blue-200 dark:border-blue-800/50 bg-blue-50 dark:bg-blue-950/40 px-4 py-2.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="text-sm font-medium text-blue-800 dark:text-blue-300">
                    {{ trans_choice(':count lead selected|:count leads selected', count($bulkSelected), ['count' => count($bulkSelected)]) }}
                </span>

                <div class="flex items-center gap-2">
                    <select wire:model="bulkStatusValue"
                            class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('Set status…') }}</option>
                        @foreach($statusOptions as $o)
                            <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="bulkSetStatus"
                            @disabled(!$bulkStatusValue)
                            class="rounded-lg bg-slate-900 dark:bg-slate-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ __('Apply') }}
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <select wire:model="bulkPriorityValue"
                            class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('Set priority…') }}</option>
                        @foreach($priorityOptions as $o)
                            <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="bulkSetPriority"
                            @disabled(!$bulkPriorityValue)
                            class="rounded-lg bg-slate-900 dark:bg-slate-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ __('Apply') }}
                    </button>
                </div>

                <button type="button" wire:click="clearBulkSelection"
                        class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors ml-auto">
                    {{ __('Clear selection') }}
                </button>
            </div>
        @endif
    @endauth

    {{-- ────────────────── table ───────────────── --}}
    @php
        $colLabels = [
            'received' => __('Received'),
            'name'     => __('Name'),
            'email'    => __('Email'),
            'phone'    => __('Phone'),
            'client'   => __('Client'),
            'source'   => __('Source'),
            'campaign' => __('Campaign'),
            'form'     => __('Form'),
            'platform' => __('Platform'),
            'status'   => __('Status'),
            'priority' => __('Priority'),
            'outreach' => __('Outreach'),
        ];
        $colWidths = [
            'received' => 'w-[160px]',
            'name' => '', 'email' => 'w-[200px]', 'phone' => 'w-[140px]',
            'client' => 'w-[140px]', 'source' => 'w-[140px]',
            'campaign' => 'w-[160px]', 'form' => 'w-[160px]', 'platform' => 'w-[110px]',
            'status' => 'w-[120px]', 'priority' => 'w-[110px]', 'outreach' => 'w-[140px]',
        ];
        $visibleCount = count($activeColumns) + count($activeQuestions)
            + (auth()->user()?->isOperator() ? 1 : 0); /* bulk checkbox */
    @endphp
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400">
                        @auth
                            @if(auth()->user()->isOperator())
                                <th class="px-3 py-2 w-8">
                                    <input type="checkbox"
                                           wire:click="bulkToggleAll"
                                           @checked(count($bulkSelected) > 0 && count($bulkSelected) === $leads->count())
                                           class="rounded border-slate-300 dark:border-slate-600 text-brand-500 focus:ring-brand-500"
                                           aria-label="{{ __('Select all on this page') }}">
                                </th>
                            @endif
                        @endauth
                        @foreach($activeColumns as $col)
                            <th class="px-3 py-2 {{ $colWidths[$col] ?? '' }} {{ $col === 'outreach' ? 'text-right' : '' }}">
                                {{ $colLabels[$col] ?? $col }}
                            </th>
                        @endforeach
                        @foreach($activeQuestions as $q)
                            <th class="px-3 py-2 w-[160px]" title="{{ $q }}">{{ \Illuminate\Support\Str::limit($q, 24) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($leads as $lead)
                        @php
                            // Index this lead's custom answers by question text for O(1) cell lookup.
                            $answersByQuestion = [];
                            foreach ((array) $lead->custom_answers as $qa) {
                                $q = trim((string) ($qa['question'] ?? ''));
                                if ($q !== '') {
                                    $answersByQuestion[$q] = (string) ($qa['answer'] ?? '');
                                }
                            }
                        @endphp
                        <tr wire:key="lead-{{ $lead->id }}"
                            wire:click="selectLead({{ $lead->id }})"
                            class="cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50 {{ $selected?->id === $lead->id ? 'bg-slate-100 dark:bg-slate-800' : '' }}">
                            @auth
                                @if(auth()->user()->isOperator())
                                    <td class="px-3 py-2" wire:click.stop>
                                        <input type="checkbox"
                                               wire:model="bulkSelected"
                                               value="{{ $lead->id }}"
                                               class="rounded border-slate-300 dark:border-slate-600 text-brand-500 focus:ring-brand-500"
                                               aria-label="{{ __('Select lead :id', ['id' => $lead->id]) }}">
                                    </td>
                                @endif
                            @endauth
                            @foreach($activeColumns as $col)
                                @switch($col)
                                    @case('received')
                                        <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                            <div>{{ $lead->created_at?->format('Y-m-d H:i') }}</div>
                                            <div class="text-xs text-slate-400 dark:text-slate-500">{{ $lead->created_at?->diffForHumans() }}</div>
                                        </td>
                                        @break
                                    @case('name')
                                        <td class="px-3 py-2 text-sm">
                                            <div class="font-medium text-slate-900 dark:text-slate-100 truncate max-w-[280px]">
                                                {{ $lead->full_name ?? '—' }}
                                            </div>
                                            @if(! in_array('email', $activeColumns, true) && ! in_array('phone', $activeColumns, true))
                                                <div class="text-xs text-slate-500 dark:text-slate-500 truncate max-w-[280px]">
                                                    {{ $lead->email ?? '' }} @if($lead->email && $lead->phone) · @endif {{ $lead->phone }}
                                                </div>
                                            @endif
                                        </td>
                                        @break
                                    @case('email')
                                        <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 truncate max-w-[220px]">{{ $lead->email ?? '—' }}</td>
                                        @break
                                    @case('phone')
                                        <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 whitespace-nowrap">{{ $lead->phone ?? '—' }}</td>
                                        @break
                                    @case('client')
                                        <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300">{{ $lead->client_name ?? '—' }}</td>
                                        @break
                                    @case('source')
                                        <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400 capitalize">{{ str_replace('_', ' ', $lead->source) }}</td>
                                        @break
                                    @case('campaign')
                                        <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 truncate max-w-[180px]" title="{{ $lead->campaign_name }}">{{ $lead->campaign_name ?? '—' }}</td>
                                        @break
                                    @case('form')
                                        <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 truncate max-w-[180px]" title="{{ $lead->form_name }}">{{ $lead->form_name ?? '—' }}</td>
                                        @break
                                    @case('platform')
                                        <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 capitalize">{{ $lead->platform ?? '—' }}</td>
                                        @break
                                    @case('status')
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $lead->status->badgeClasses() }}">
                                                {{ $lead->status->label() }}
                                            </span>
                                        </td>
                                        @break
                                    @case('priority')
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $lead->priority->badgeClasses() }}">
                                                {{ $lead->priority->label() }}
                                            </span>
                                        </td>
                                        @break
                                    @case('outreach')
                                        <td class="px-3 py-2 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                @if($lead->qualified_at)
                                                    <span aria-label="{{ __('Qualified') }}" title="{{ __('Qualified · :when', ['when' => $lead->qualified_at->format('Y-m-d H:i')]) }}"
                                                          class="inline-flex items-center rounded bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-600/20 dark:ring-emerald-500/30">Q</span>
                                                @endif
                                                @if($lead->called_at)
                                                    <span aria-label="{{ __('Called') }}" title="{{ __('Called · :when', ['when' => $lead->called_at->format('Y-m-d H:i')]) }}"
                                                          class="inline-flex items-center rounded bg-sky-50 dark:bg-sky-950/60 px-1.5 py-0.5 text-[10px] font-bold text-sky-700 dark:text-sky-400 ring-1 ring-sky-600/20 dark:ring-sky-500/30">C</span>
                                                @endif
                                                @if($lead->mailed_at)
                                                    <span aria-label="{{ __('Mailed') }}" title="{{ __('Mailed · :when', ['when' => $lead->mailed_at->format('Y-m-d H:i')]) }}"
                                                          class="inline-flex items-center rounded bg-indigo-50 dark:bg-indigo-950/60 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:text-indigo-400 ring-1 ring-indigo-600/20 dark:ring-indigo-500/30">M</span>
                                                @endif
                                                @if($lead->duplicate_flag)
                                                    <span aria-label="{{ __('Potential duplicate') }}" title="{{ __('Potential duplicate') }}"
                                                          class="inline-flex items-center rounded bg-rose-50 dark:bg-rose-950/60 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 dark:text-rose-400 ring-1 ring-rose-600/20 dark:ring-rose-500/30">DUP</span>
                                                @endif
                                            </div>
                                        </td>
                                        @break
                                @endswitch
                            @endforeach
                            @foreach($activeQuestions as $q)
                                <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 truncate max-w-[180px]" title="{{ $answersByQuestion[$q] ?? '' }}">
                                    {{ $answersByQuestion[$q] ?? '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $visibleCount }}"
                                class="px-3 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('No leads match these filters yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 dark:border-slate-700/50 px-3 py-2">
            {{ $leads->links() }}
        </div>
    </div>

    {{-- ────────────────── detail side panel ───────────────── --}}
    @if($selected)
        <x-lead-panel :lead="$selected" :statusOptions="$statusOptions" :priorityOptions="$priorityOptions" :aiSummary="$leadAiSummary" />
    @endif

    {{-- ────────────────── new lead modal ───────────────── --}}
    @if($showManualForm)
        <x-manual-lead-modal :priorityOptions="$priorityOptions" :form="$manual" />
    @endif
</div>
