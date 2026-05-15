<div class="space-y-6">
    {{-- ────────────────── header / KPI row ───────────────── --}}
    <div class="flex flex-col gap-4">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">Lead inbox</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @auth
                        @if(auth()->user()->isClient())
                            Your leads across all configured sources.
                        @else
                            All leads across all sources for this workspace.
                        @endif
                    @endauth
                </p>
            </div>

            @auth
                @if(auth()->user()->isOperator())
                    <button type="button" wire:click="openManualForm"
                            class="inline-flex items-center gap-2 rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                        + New lead
                    </button>
                @endif
            @endauth
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <x-kpi-card label="New"        :value="$kpis['new']"        tone="blue"  />
            <x-kpi-card label="Duplicates" :value="$kpis['duplicates']" tone="rose"  />
            <x-kpi-card label="Incomplete" :value="$kpis['incomplete']" tone="amber" />
            <x-kpi-card label="Total"      :value="$kpis['total']"      tone="slate" />
        </div>

        @if($kpis['by_source']->isNotEmpty())
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 px-4 py-3 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 font-medium mb-2">Leads by source</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($kpis['by_source'] as $row)
                        <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ $row->source }}
                            <span class="text-slate-400 dark:text-slate-500">·</span>
                            <span>{{ $row->total }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ────────────────── filters ───────────────── --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-3 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-4">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Search name, email, phone, message…"
                       class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select wire:model.live="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All</option>
                    @foreach($statusOptions as $o)
                        <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Priority</label>
                <select wire:model.live="priority" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All</option>
                    @foreach($priorityOptions as $o)
                        <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Source</label>
                <select wire:model.live="source" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All</option>
                    @foreach($sourceOptions as $o)
                        <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Client</label>
                <select wire:model.live="client" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All</option>
                    @foreach($clientOptions as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <div class="flex items-center gap-3">
                <span>Sort:</span>
                <select wire:model.live="sort" class="rounded-lg border-slate-300 text-xs focus:border-brand-500 focus:ring-brand-500">
                    <option value="created_desc">Newest first</option>
                    <option value="created_asc">Oldest first</option>
                    <option value="priority_desc">Priority (high→low)</option>
                </select>
            </div>
            <div class="flex items-center gap-3">
                @if(!$showSaveDialog)
                    <button type="button" wire:click="openSaveDialog"
                            class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">Save view</button>
                @endif
                <button type="button" wire:click="clearFilters"
                        class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">Clear filters</button>
            </div>
        </div>

        {{-- ── save-filter inline form ── --}}
        @if($showSaveDialog)
            <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700/50 flex flex-wrap items-center gap-2">
                <input type="text" wire:model="newFilterName" placeholder="Filter name…"
                       maxlength="100" autocomplete="off"
                       class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 w-44">
                @error('newFilterName')
                    <span class="text-xs text-red-600">{{ $message }}</span>
                @enderror
                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">
                    <input type="checkbox" wire:model="newFilterIsDefault"
                           class="rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                    Default view
                </label>
                <button type="button" wire:click="saveFilter"
                        class="rounded-lg bg-slate-900 dark:bg-slate-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">
                    Save
                </button>
                <button type="button" wire:click="closeSaveDialog"
                        class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">Cancel</button>
            </div>
        @endif

        {{-- ── saved filter chips ── --}}
        @if($savedFilters->isNotEmpty())
            <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700/50 flex flex-wrap items-center gap-2">
                <span class="text-xs text-slate-400 dark:text-slate-500">Saved:</span>
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
                                title="{{ $sf->is_default ? 'Default view – click to remove' : 'Set as default view' }}"
                                class="{{ $sf->is_default ? 'text-amber-500 hover:text-amber-600' : 'text-slate-300 dark:text-slate-600 hover:text-amber-400' }} leading-none px-0.5">
                            ★
                        </button>
                        <button type="button" wire:click="deleteFilter({{ $sf->id }})"
                                aria-label="Delete {{ $sf->name }}"
                                title="Delete"
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
                    {{ count($bulkSelected) }} {{ count($bulkSelected) === 1 ? 'lead' : 'leads' }} selected
                </span>

                <div class="flex items-center gap-2">
                    <select wire:model="bulkStatusValue"
                            class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Set status…</option>
                        @foreach($statusOptions as $o)
                            <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="bulkSetStatus"
                            @disabled(!$bulkStatusValue)
                            class="rounded-lg bg-slate-900 dark:bg-slate-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        Apply
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <select wire:model="bulkPriorityValue"
                            class="rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Set priority…</option>
                        @foreach($priorityOptions as $o)
                            <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="bulkSetPriority"
                            @disabled(!$bulkPriorityValue)
                            class="rounded-lg bg-slate-900 dark:bg-slate-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        Apply
                    </button>
                </div>

                <button type="button" wire:click="clearBulkSelection"
                        class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors ml-auto">
                    Clear selection
                </button>
            </div>
        @endif
    @endauth

    {{-- ────────────────── table ───────────────── --}}
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
                                           aria-label="Select all on this page">
                                </th>
                            @endif
                        @endauth
                        <th class="px-3 py-2 w-[160px]">Received</th>
                        <th class="px-3 py-2">Contact</th>
                        <th class="px-3 py-2 w-[140px]">Client</th>
                        <th class="px-3 py-2 w-[140px]">Source</th>
                        <th class="px-3 py-2 w-[120px]">Status</th>
                        <th class="px-3 py-2 w-[110px]">Priority</th>
                        <th class="px-3 py-2 w-[80px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($leads as $lead)
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
                                               aria-label="Select lead {{ $lead->id }}">
                                    </td>
                                @endif
                            @endauth
                            <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                <div>{{ $lead->created_at?->format('Y-m-d H:i') }}</div>
                                <div class="text-xs text-slate-400 dark:text-slate-500">{{ $lead->created_at?->diffForHumans() }}</div>
                            </td>
                            <td class="px-3 py-2 text-sm">
                                <div class="font-medium text-slate-900 dark:text-slate-100 truncate max-w-[280px]">
                                    {{ $lead->full_name ?? '—' }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-500 truncate max-w-[280px]">
                                    {{ $lead->email ?? '' }} @if($lead->email && $lead->phone) · @endif {{ $lead->phone }}
                                </div>
                            </td>
                            <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300">{{ $lead->client_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400 capitalize">{{ str_replace('_', ' ', $lead->source) }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $lead->status->badgeClasses() }}">
                                    {{ $lead->status->label() }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $lead->priority->badgeClasses() }}">
                                    {{ $lead->priority->label() }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($lead->duplicate_flag)
                                    <span aria-label="Potential duplicate" title="Potential duplicate"
                                          class="inline-flex items-center rounded bg-rose-50 dark:bg-rose-950/60 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 dark:text-rose-400 ring-1 ring-rose-600/20 dark:ring-rose-500/30">DUP</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->isOperator() ? 8 : 7 }}"
                                class="px-3 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                No leads match these filters yet.
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
        <x-lead-panel :lead="$selected" :statusOptions="$statusOptions" :priorityOptions="$priorityOptions" />
    @endif

    {{-- ────────────────── new lead modal ───────────────── --}}
    @if($showManualForm)
        <x-manual-lead-modal :priorityOptions="$priorityOptions" :form="$manual" />
    @endif
</div>
