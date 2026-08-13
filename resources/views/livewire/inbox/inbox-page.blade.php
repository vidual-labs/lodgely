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

                {{-- Export CSV is available to clients and operators alike (each scoped
                     to their own visible leads); Export JSON and manual lead entry stay
                     operator-only. --}}
                @auth
                    <div class="flex items-center gap-2" wire:ignore.self>
                        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                            <a href="{{ route('inbox.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                               class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                {{ __('Export CSV') }}
                            </a>
                            @if(auth()->user()->isOperator())
                                <a href="{{ route('inbox.export', array_merge(request()->query(), ['format' => 'ndjson'])) }}"
                                   class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors border-l border-slate-200 dark:border-slate-700">
                                    {{ __('Export JSON') }}
                                </a>
                            @endif
                        </div>
                        @if(auth()->user()->isOperator())
                            <button type="button" wire:click="openManualForm"
                                    class="inline-flex items-center gap-2 rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                                + {{ __('New lead') }}
                            </button>
                        @endif
                    </div>
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
             class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-3">
            <x-kpi-card :label="__('New')"        :value="$kpis['new']"        tone="blue"   />
            <x-kpi-card :label="__('Offer sent')" :value="$kpis['offer_sent']" tone="violet" />
            <x-kpi-card :label="__('Duplicates')" :value="$kpis['duplicates']" tone="rose"   />
            <x-kpi-card :label="__('Incomplete')" :value="$kpis['incomplete']" tone="amber"  />
            <x-kpi-card :label="__('Total')"      :value="$kpis['total']"      tone="slate"  />
        </div>
    </div>

    {{-- ────────────────── filter bar + sources ───────────────── --}}
    @php
        $savedFilterErrors = $errors->hasAny(['name', 'is_default', 'search', 'status', 'priority', 'source', 'client', 'outreach', 'sort']);
        $savedFilterFlash = session('inbox.saved-filter.stored');
        $filterPickerFlash = session('inbox.filters.saved');
        // Which panel (if any) should reopen on this load — a one-shot session
        // flash set by the controller that just handled the form submit, NOT a
        // query param. A query param would stick in the address bar forever
        // (nothing ever clears it, and none of columns/filters/saved-views are
        // Livewire #[Url]-bound properties Livewire itself would manage), so
        // the panel would keep reopening on every future visit to that URL —
        // that was the "columns picker stays open forever" bug.
        $openPanel = session('inbox.open-panel');
        $initialSourcesOpen = false;
        $initialSavedOpen = $savedFilters->isNotEmpty() && $openPanel === 'saved-views';
        $initialColumnsOpen = $openPanel === 'columns';
        $initialFiltersOpen = $openPanel === 'filters';
        $initialSaveOpen = request()->boolean('save') || $savedFilterErrors || (bool) $savedFilterFlash;
    @endphp
    {{-- Filter card. The five expandable panels — Sources, Saved views, --}}
    {{-- Custom columns, Filter options, Save current view — all open --}}
    {{-- inline below the toolbar with the same border-t / mt-3 / pt-3 --}}
    {{-- rhythm. Open state lives on the parent x-data so Livewire morphs --}}
    {{-- don't reset it. Server-side writes (Apply columns / Apply filters / --}}
    {{-- Save view) go through plain HTML forms to their own controllers — --}}
    {{-- Livewire dropdowns dropped clicks in this subtree in production; --}}
    {{-- see CLAUDE.md. --}}
    <div x-data="{ sourcesOpen: @json($initialSourcesOpen), savedOpen: @json($initialSavedOpen), columnsOpen: @json($initialColumnsOpen), filtersOpen: @json($initialFiltersOpen), saveOpen: @json($initialSaveOpen) }"
         class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-3 shadow-sm">

        {{-- ── toolbar row ── --}}
        @php
            $activeFilterCount = (int)($search !== '') + (int)($status !== '')
                + (int)($priority !== '') + (int)($source !== '') + (int)($client !== '') + (int)($outreach !== '');
        @endphp
        <div class="flex flex-wrap items-center gap-2">

            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search name, email, phone, message…') }}"
                   class="min-w-[160px] grow rounded-lg border-slate-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 px-2.5">

            @if(in_array('status', $pickedFilters, true))
                <select wire:model.live="status"
                        class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 pl-2.5 pr-7">
                    <option value="">{{ __('Status') }}</option>
                    @foreach($statusGroups as $group)
                        <optgroup label="{{ $group['label'] }}">
                            @foreach($group['options'] as $o)
                                <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            @endif

            @if(in_array('priority', $pickedFilters, true))
                <select wire:model.live="priority"
                        class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 pl-2.5 pr-7">
                    <option value="">{{ __('Priority') }}</option>
                    @foreach($priorityOptions as $o)
                        <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                    @endforeach
                </select>
            @endif

            @if(in_array('source', $pickedFilters, true))
                <select wire:model.live="source"
                        class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 pl-2.5 pr-7">
                    <option value="">{{ __('Source') }}</option>
                    @foreach($sourceOptions as $o)
                        <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                    @endforeach
                </select>
            @endif

            @if(in_array('outreach', $pickedFilters, true))
                <select wire:model.live="outreach"
                        class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 h-8 py-0 pl-2.5 pr-7">
                    <option value="">{{ __('Outreach') }}</option>
                    <option value="not_contacted">{{ __('Not contacted') }}</option>
                    <option value="qualified">{{ __('Qualified') }}</option>
                    <option value="called">{{ __('Called') }}</option>
                    <option value="mailed">{{ __('Mailed') }}</option>
                </select>
            @endif

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

            {{-- right-side: lead count · Show group · actions. Each toolbar --}}
            {{-- button carries its own px-1.5 padding so the items have --}}
            {{-- visible breathing room even if a stale CSS bundle hasn't --}}
            {{-- picked up the gap utility yet. Visible ·/| separators give --}}
            {{-- the group rhythm on top of that. --}}
            <div class="flex flex-wrap items-center gap-x-1 gap-y-2 w-full sm:w-auto sm:ml-auto sm:justify-end text-xs text-slate-500 dark:text-slate-400">
                {{-- lead count --}}
                <span class="tabular-nums text-slate-400 dark:text-slate-500 select-none px-1.5 py-0.5">
                    {{ number_format($leads->total()) }}&thinsp;{{ trans_choice('lead|leads', $leads->total()) }}
                </span>

                <span class="text-slate-300 dark:text-slate-600 select-none" aria-hidden="true">·</span>

                <span class="text-slate-400 dark:text-slate-500 select-none px-1.5 py-0.5">{{ __('Show:') }}</span>
                @if($kpis['by_source']->isNotEmpty())
                    <button type="button" @click="sourcesOpen = !sourcesOpen"
                            :class="sourcesOpen ? 'text-slate-800 dark:text-slate-100 font-medium' : ''"
                            class="px-1.5 py-0.5 rounded hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Sources') }}</button>
                @endif
                @if($savedFilters->isNotEmpty())
                    <button type="button" @click="savedOpen = !savedOpen"
                            :class="savedOpen ? 'text-slate-800 dark:text-slate-100 font-medium' : ''"
                            class="px-1.5 py-0.5 rounded hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Saved views') }}</button>
                @endif
                <button type="button" @click="columnsOpen = !columnsOpen"
                        :class="columnsOpen ? 'text-slate-800 dark:text-slate-100 font-medium' : ''"
                        class="px-1.5 py-0.5 rounded hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Columns') }}</button>

                <button type="button" @click="filtersOpen = !filtersOpen"
                        :class="filtersOpen ? 'text-slate-800 dark:text-slate-100 font-medium' : ''"
                        class="px-1.5 py-0.5 rounded hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Filter options') }}</button>

                <button type="button" @click="saveOpen = !saveOpen"
                        :class="saveOpen ? 'text-slate-800 dark:text-slate-100 font-medium' : ''"
                        class="px-1.5 py-0.5 rounded hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Save current view') }}</button>

                <span class="text-slate-300 dark:text-slate-600 select-none" aria-hidden="true">·</span>

                <button type="button" wire:click="clearFilters"
                        @class([
                            'px-1.5 py-0.5 rounded transition-colors',
                            'text-brand-600 dark:text-brand-400 font-medium hover:text-brand-500 dark:hover:text-brand-200' => $activeFilterCount > 0,
                            'text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200' => $activeFilterCount === 0,
                        ])>{{ __('Clear') }}</button>
            </div>
        </div>

        {{-- All four expansion panels below share the same mt-3 pt-3 border-t --}}
        {{-- rhythm so the filter card reads as one cohesive surface across --}}
        {{-- mobile and desktop. --}}

        {{-- ── sources panel ── --}}
        {{-- Each chip is an anchor link that sets ?source=… in the URL — --}}
        {{-- Livewire's #[Url] binding on $source picks it up on the next --}}
        {{-- request, so the inbox table filters down to that source. Pure --}}
        {{-- navigation, no wire:click. The currently-applied source chip --}}
        {{-- carries a stronger background so the user can see what's active --}}
        {{-- (and click it again to clear). --}}
        @if($kpis['by_source']->isNotEmpty())
            <div x-show="sourcesOpen" x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 dark:text-slate-500 select-none">{{ __('Sources') }}</span>
                    <button type="button" @click="sourcesOpen = false"
                            class="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 transition-colors px-1.5 py-0.5"
                            aria-label="{{ __('Close') }}">{{ __('Close') }}</button>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                @foreach($kpis['by_source'] as $row)
                    @php
                        $isActive = $source === $row->source;
                        $sourceHref = route('inbox', array_filter(array_merge(request()->only(['q', 'status', 'priority', 'client', 'sort']), [
                            'source' => $isActive ? null : $row->source,
                        ]), fn ($v) => $v !== null && $v !== ''));
                    @endphp
                    <a href="{{ $sourceHref }}"
                       class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors
                              {{ $isActive
                                  ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-200 dark:bg-slate-200 dark:text-slate-900'
                                  : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                        <span>{{ $row->source }}</span>
                        <span class="font-normal {{ $isActive ? 'text-slate-200 dark:text-slate-500' : 'text-slate-400 dark:text-slate-500' }}">{{ $row->total }}</span>
                    </a>
                @endforeach
                </div>
            </div>
        @endif

        {{-- ── custom columns picker ── --}}
        {{-- Pure HTML <form> POSTing to InboxColumnPickerController. See --}}
        {{-- CLAUDE.md for why this isn't a Livewire wire:model.live panel. --}}
        <div x-show="columnsOpen" x-cloak
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800">
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
            {{-- Alpine keeps the (n / max) counters live as chips are toggled and
                 warns when over the cap — the server would otherwise silently
                 keep only the first MAX_TOTAL_COLUMNS picks. Display-only state;
                 the write still goes through the native form POST. --}}
            <form method="POST" action="{{ route('inbox.columns.update') }}" class="space-y-3"
                  x-data="{ picked: {{ $total }}, qs: {{ count($pickedQs) }}, max: {{ \App\Livewire\Inbox\InboxPage::MAX_TOTAL_COLUMNS }}, maxQs: {{ \App\Livewire\Inbox\InboxPage::MAX_QUESTION_COLUMNS }} }"
                  @change="picked = $el.querySelectorAll('input:checked').length; qs = $el.querySelectorAll(`input[name='questions[]']:checked`).length">
                @csrf
                {{-- Preserve the current filter state across the redirect — see
                     InboxColumnPickerController; without these, applying a
                     column pick silently reset every active filter. --}}
                <input type="hidden" name="search"   value="{{ $search }}">
                <input type="hidden" name="status"   value="{{ $status }}">
                <input type="hidden" name="priority" value="{{ $priority }}">
                <input type="hidden" name="source"   value="{{ $source }}">
                <input type="hidden" name="client"   value="{{ $client }}">
                <input type="hidden" name="outreach" value="{{ $outreach }}">
                <input type="hidden" name="sort"     value="{{ $sort }}">

                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="text-xs text-slate-600 dark:text-slate-400">
                        {{ __('Visible columns') }}
                        <span class="ml-1 text-slate-400 dark:text-slate-500"
                              :class="picked > max && 'text-amber-600 dark:text-amber-400 font-medium'">
                            (<span x-text="picked">{{ $total }}</span> / {{ \App\Livewire\Inbox\InboxPage::MAX_TOTAL_COLUMNS }})
                        </span>
                        <span class="ml-1 text-slate-400 dark:text-slate-500 hidden sm:inline">
                            · {{ __('Up to :tot total, :q custom questions', ['tot' => \App\Livewire\Inbox\InboxPage::MAX_TOTAL_COLUMNS, 'q' => \App\Livewire\Inbox\InboxPage::MAX_QUESTION_COLUMNS]) }}
                        </span>
                    </div>
                    <button type="button" @click="columnsOpen = false"
                            class="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 transition-colors px-1.5 py-0.5"
                            aria-label="{{ __('Close') }}">{{ __('Close') }}</button>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    @foreach(\App\Livewire\Inbox\InboxPage::AVAILABLE_COLUMNS as $key)
                        @php $isOn = in_array($key, $picked, true); @endphp
                        <label class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors cursor-pointer select-none
                                      border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700
                                      has-[:checked]:border-slate-900 has-[:checked]:bg-slate-900 has-[:checked]:text-white
                                      dark:has-[:checked]:border-slate-200 dark:has-[:checked]:bg-slate-200 dark:has-[:checked]:text-slate-900">
                            <input type="checkbox" name="columns[]" value="{{ $key }}" @checked($isOn)
                                   class="peer sr-only">
                            <span aria-hidden="true" class="peer-checked:hidden">+</span>
                            <span aria-hidden="true" class="hidden peer-checked:inline">✓</span>
                            <span>{{ $colLabelsPicker[$key] ?? $key }}</span>
                        </label>
                    @endforeach
                </div>

                @if(!empty($availableQuestions))
                    <div>
                        <div class="text-xs text-slate-600 dark:text-slate-400 mb-1.5">
                            {{ __('Custom form questions') }}
                            <span class="ml-1 text-slate-400 dark:text-slate-500"
                                  :class="qs > maxQs && 'text-amber-600 dark:text-amber-400 font-medium'">
                                (<span x-text="qs">{{ count($pickedQs) }}</span> / {{ \App\Livewire\Inbox\InboxPage::MAX_QUESTION_COLUMNS }})
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($availableQuestions as $q)
                                @php $isOn = in_array($q, $pickedQs, true); @endphp
                                <label title="{{ $q }}"
                                       class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors cursor-pointer select-none
                                              border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700
                                              has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-600 has-[:checked]:text-white
                                              dark:has-[:checked]:border-indigo-500 dark:has-[:checked]:bg-indigo-500">
                                    <input type="checkbox" name="questions[]" value="{{ $q }}" @checked($isOn)
                                           class="peer sr-only">
                                    <span aria-hidden="true" class="peer-checked:hidden">+</span>
                                    <span aria-hidden="true" class="hidden peer-checked:inline">✓</span>
                                    <span>{{ \Illuminate\Support\Str::limit($q, 32) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        {{ __('No custom-question columns available — leads with form answers will populate this list automatically.') }}
                    </p>
                @endif

                <p x-show="picked > max || qs > maxQs" x-cloak
                   class="text-[11px] text-amber-600 dark:text-amber-400">
                    {{ __('Too many columns selected — only the first picks within the limit will be kept.') }}
                </p>

                {{-- Reset sits flush left, away from Apply, so a misclick can't wipe the picks. --}}
                <div class="flex items-center justify-between gap-2 pt-1">
                    <button type="submit" name="action" value="reset"
                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors px-1.5 py-0.5">{{ __('Reset to defaults') }}</button>
                    <button type="submit" name="action" value="apply"
                            class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">{{ __('Apply') }}</button>
                </div>

                @if(session('inbox.columns.saved'))
                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400">{{ __('Saved.') }}</p>
                @endif
            </form>
        </div>

        {{-- ── filter-dropdown picker ── --}}
        {{-- Which of Status/Priority/Source/Outreach show up as toolbar --}}
        {{-- dropdowns — separate from *values* of those filters. Same --}}
        {{-- native-form pattern as the columns picker just above, and the --}}
        {{-- same reason: see CLAUDE.md. Hidden inputs carry the current --}}
        {{-- filter state so applying a picker change doesn't drop whatever --}}
        {{-- the user was already looking at; InboxFilterPickerController --}}
        {{-- additionally drops the *value* of any filter just unchecked --}}
        {{-- here, so a hidden dropdown can never stay invisibly active. --}}
        <div x-show="filtersOpen" x-cloak
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            @php
                $filterLabelsPicker = [
                    'status' => __('Status'), 'priority' => __('Priority'),
                    'source' => __('Source'), 'outreach' => __('Outreach'),
                ];
            @endphp
            <form method="POST" action="{{ route('inbox.filters.update') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="search"   value="{{ $search }}">
                <input type="hidden" name="status"   value="{{ $status }}">
                <input type="hidden" name="priority" value="{{ $priority }}">
                <input type="hidden" name="source"   value="{{ $source }}">
                <input type="hidden" name="client"   value="{{ $client }}">
                <input type="hidden" name="outreach" value="{{ $outreach }}">
                <input type="hidden" name="sort"     value="{{ $sort }}">

                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-xs text-slate-600 dark:text-slate-400">{{ __('Visible filters') }}</span>
                    <button type="button" @click="filtersOpen = false"
                            class="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 transition-colors px-1.5 py-0.5"
                            aria-label="{{ __('Close') }}">{{ __('Close') }}</button>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    @foreach(\App\Livewire\Inbox\InboxPage::AVAILABLE_FILTERS as $key)
                        @php $isOn = in_array($key, $pickedFilters, true); @endphp
                        <label class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors cursor-pointer select-none
                                      border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700
                                      has-[:checked]:border-slate-900 has-[:checked]:bg-slate-900 has-[:checked]:text-white
                                      dark:has-[:checked]:border-slate-200 dark:has-[:checked]:bg-slate-200 dark:has-[:checked]:text-slate-900">
                            <input type="checkbox" name="filters[]" value="{{ $key }}" @checked($isOn)
                                   class="peer sr-only">
                            <span aria-hidden="true" class="peer-checked:hidden">+</span>
                            <span aria-hidden="true" class="hidden peer-checked:inline">✓</span>
                            <span>{{ $filterLabelsPicker[$key] ?? $key }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="flex items-center justify-between gap-2 pt-1">
                    <button type="submit" name="action" value="reset"
                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors px-1.5 py-0.5">{{ __('Reset to defaults') }}</button>
                    <button type="submit" name="action" value="apply"
                            class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">{{ __('Apply') }}</button>
                </div>

                @if($filterPickerFlash)
                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400">{{ __('Saved.') }}</p>
                @endif
            </form>
        </div>

        {{-- ── saved filter chips ── --}}
        {{-- Each chip is a tiny three-button HTML form posting to --}}
        {{-- InboxSavedFilterController@action — load / star / delete. Same --}}
        {{-- "native form, not wire:click" rationale as the rest of the card. --}}
        @if($savedFilters->isNotEmpty())
            <div x-show="savedOpen" x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 dark:text-slate-500 select-none">{{ __('Saved views') }}</span>
                    <button type="button" @click="savedOpen = false"
                            class="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 transition-colors px-1.5 py-0.5"
                            aria-label="{{ __('Close') }}">{{ __('Close') }}</button>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                @foreach($savedFilters as $sf)
                    <form method="POST" action="{{ route('inbox.saved-filters.action', $sf) }}"
                          class="inline-flex items-center rounded-full border pl-2.5 pr-1 py-0.5 text-xs gap-1 transition-colors
                                 {{ $sf->is_default
                                     ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/50'
                                     : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800' }}">
                        @csrf
                        <button type="submit" name="action" value="load"
                                class="font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100 max-w-[140px] truncate transition-colors"
                                title="{{ $sf->name }}">
                            {{ $sf->name }}
                        </button>
                        <button type="submit" name="action" value="default"
                                aria-label="{{ $sf->is_default ? __('Remove as default view') : __('Set as default view') }}"
                                title="{{ $sf->is_default ? __('Remove as default view') : __('Set as default view') }}"
                                class="{{ $sf->is_default ? 'text-amber-500 hover:text-amber-600' : 'text-slate-300 dark:text-slate-600 hover:text-amber-400' }} leading-none px-0.5">
                            ★
                        </button>
                        <button type="submit" name="action" value="delete"
                                aria-label="{{ __('Delete') }} {{ $sf->name }}"
                                title="{{ __('Delete') }}"
                                onclick="return confirm({{ json_encode(__('Delete saved view :name?', ['name' => $sf->name])) }})"
                                class="text-slate-300 dark:text-slate-600 hover:text-red-500 leading-none px-0.5">
                            ×
                        </button>
                    </form>
                @endforeach
                </div>
            </div>
        @endif

        {{-- ── save current view ── --}}
        {{-- Plain HTML form to InboxSavedFilterController. Hidden inputs --}}
        {{-- carry the current filter URL state so the saved view captures --}}
        {{-- exactly what the user is looking at. See CLAUDE.md for why this --}}
        {{-- isn't a Livewire dialog. --}}
        <div x-show="saveOpen" x-cloak
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 dark:text-slate-500 select-none">{{ __('Save current view') }}</span>
                    <button type="button" @click="saveOpen = false"
                            class="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 transition-colors px-1.5 py-0.5"
                            aria-label="{{ __('Close') }}">{{ __('Close') }}</button>
                </div>
            <form method="POST" action="{{ route('inbox.saved-filters.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="search"   value="{{ $search }}">
                <input type="hidden" name="status"   value="{{ $status }}">
                <input type="hidden" name="priority" value="{{ $priority }}">
                <input type="hidden" name="source"   value="{{ $source }}">
                <input type="hidden" name="client"   value="{{ $client }}">
                <input type="hidden" name="outreach" value="{{ $outreach }}">
                <input type="hidden" name="sort"     value="{{ $sort }}">

                <div class="space-y-1.5">
                    <label for="saved-filter-name" class="block text-xs text-slate-600 dark:text-slate-400">{{ __('Name this view') }}</label>
                    <input type="text" id="saved-filter-name" name="name"
                           value="{{ old('name') }}"
                           placeholder="{{ __('e.g. New leads this week') }}"
                           maxlength="100" autocomplete="off" required
                           class="block w-full sm:max-w-sm rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('name')
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <label class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default'))
                           class="rounded border-slate-300 dark:border-slate-600 text-brand-500 focus:ring-brand-500">
                    {{ __('Set as my default view') }}
                </label>

                <div class="flex items-center justify-end gap-3 pt-1">
                    <button type="button" @click="saveOpen = false"
                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">{{ __('Cancel') }}</button>
                    <button type="submit"
                            class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">{{ __('Save view') }}</button>
                </div>

                @if($savedFilterFlash)
                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400">{{ $savedFilterFlash }}</p>
                @endif
            </form>
        </div>
    </div>

    {{-- ────────────────── bulk action bar ───────────────── --}}
    {{-- Status/priority bulk edit is available to clients and operators alike;
         bulk delete (below) stays operator-only. --}}
    @auth
        @if(count($bulkSelected) > 0)
            <div class="rounded-xl border border-blue-200 dark:border-blue-800/50 bg-blue-50 dark:bg-blue-950/40 px-4 py-2.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="text-sm font-medium text-blue-800 dark:text-blue-300">
                    {{ trans_choice(':count lead selected|:count leads selected', count($bulkSelected), ['count' => count($bulkSelected)]) }}
                </span>

                <div class="flex items-center gap-2">
                    <select wire:model="bulkStatusValue"
                            class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('Set status…') }}</option>
                        @foreach($statusGroups as $group)
                            <optgroup label="{{ $group['label'] }}">
                                @foreach($group['options'] as $o)
                                    <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                                @endforeach
                            </optgroup>
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

                @if(auth()->user()->isOperator())
                    <button type="button"
                            wire:click="bulkDelete"
                            wire:confirm="{{ trans_choice('Delete :count selected lead? This cannot be undone.|Delete :count selected leads? This cannot be undone.', count($bulkSelected), ['count' => count($bulkSelected)]) }}"
                            class="rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-rose-700 transition-colors">
                        {{ __('Delete') }}
                    </button>
                @endif

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
            + (auth()->check() ? 1 : 0); /* bulk checkbox — clients and operators alike */
        $sortableColumns = \App\Domain\Leads\Services\LeadFilter::sortableColumns();
    @endphp
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400">
                        @auth
                            @php
                                $allOnPageSelected = count($bulkSelected) > 0 && count($bulkSelected) === $leads->count();
                                $someSelected = count($bulkSelected) > 0 && !$allOnPageSelected;
                            @endphp
                            <th class="px-3 py-2 w-8">
                                <button type="button" wire:click="bulkToggleAll"
                                        class="flex items-center justify-center w-5 h-5 rounded border transition-colors cursor-pointer
                                               {{ $allOnPageSelected || $someSelected
                                                   ? 'bg-brand-500 border-brand-500 text-white'
                                                   : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500' }}"
                                        aria-label="{{ __('Select all on this page') }}">
                                    @if($allOnPageSelected)
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 14 14" stroke="currentColor" stroke-width="2.5"><path d="M3 7l3 3 5-6"/></svg>
                                    @elseif($someSelected)
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 14 14" stroke="currentColor" stroke-width="2.5"><path d="M3 7h8"/></svg>
                                    @endif
                                </button>
                            </th>
                        @endauth
                        @foreach($activeColumns as $col)
                            <th class="px-3 py-2 {{ $colWidths[$col] ?? '' }} {{ $col === 'outreach' ? 'text-right' : '' }}">
                                @if(isset($sortableColumns[$col]))
                                    @php
                                        $colSortAsc = $sortableColumns[$col]['asc'];
                                        $colSortDesc = $sortableColumns[$col]['desc'];
                                        $isAsc = $sort === $colSortAsc;
                                        $isDesc = $sort === $colSortDesc;
                                        $nextSort = $isAsc ? $colSortDesc : ($isDesc ? 'created_desc' : $colSortAsc);
                                    @endphp
                                    <button type="button" wire:click="$set('sort', '{{ $nextSort }}')"
                                            class="inline-flex items-center gap-1 group hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                                        <span>{{ $colLabels[$col] ?? $col }}</span>
                                        <span class="inline-flex flex-col -space-y-1 leading-none">
                                            <svg class="w-3 h-3 {{ $isAsc ? 'text-brand-600 dark:text-brand-400' : 'text-slate-300 dark:text-slate-600 group-hover:text-slate-400 dark:group-hover:text-slate-500' }}" viewBox="0 0 12 12" fill="currentColor"><path d="M6 3L10 8H2L6 3Z"/></svg>
                                            <svg class="w-3 h-3 {{ $isDesc ? 'text-brand-600 dark:text-brand-400' : 'text-slate-300 dark:text-slate-600 group-hover:text-slate-400 dark:group-hover:text-slate-500' }}" viewBox="0 0 12 12" fill="currentColor"><path d="M6 9L2 4H10L6 9Z"/></svg>
                                        </span>
                                    </button>
                                @else
                                    {{ $colLabels[$col] ?? $col }}
                                @endif
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
                                <td class="px-3 py-2" wire:click.stop>
                                    @php $isRowSelected = in_array((string) $lead->id, $bulkSelected, true); @endphp
                                    <button type="button" wire:click="toggleBulkItem('{{ $lead->id }}')"
                                            class="flex items-center justify-center w-5 h-5 rounded border transition-colors cursor-pointer
                                                   {{ $isRowSelected
                                                       ? 'bg-brand-500 border-brand-500 text-white'
                                                       : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500' }}"
                                            aria-label="{{ __('Select lead :id', ['id' => $lead->id]) }}">
                                        @if($isRowSelected)
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 14 14" stroke="currentColor" stroke-width="2.5"><path d="M3 7l3 3 5-6"/></svg>
                                        @endif
                                    </button>
                                </td>
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

        <div class="border-t border-slate-200 dark:border-slate-700/50 px-4 py-3">
            {{ $leads->links() }}
        </div>
    </div>

    {{-- ────────────────── detail side panel ───────────────── --}}
    @if($selected)
        <x-lead-panel :lead="$selected" :statusOptions="$statusOptions" :statusGroups="$statusGroups"
                      :priorityOptions="$priorityOptions" :noteSnippets="$noteSnippets" :aiSummary="$leadAiSummary" />
    @endif

    {{-- ────────────────── new lead modal ───────────────── --}}
    @if($showManualForm)
        <x-manual-lead-modal :priorityOptions="$priorityOptions" :form="$manual" />
    @endif
</div>
