<div class="space-y-6 max-w-4xl">

    {{-- ── LIST MODE ──────────────────────────────────────────────────────── --}}
    @if($mode === 'list')
        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Google Sheets') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Pull leads automatically from one or more Google Sheets. Each sheet is fetched on its own schedule.') }}
                </p>
            </div>
            <button type="button" wire:click="openCreate"
                    class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                {{ __('Add sheet') }}
            </button>
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 text-left">{{ __('Label') }}</th>
                        <th class="px-4 py-2 text-left hidden sm:table-cell">{{ __('Spreadsheet ID') }}</th>
                        <th class="px-4 py-2 text-left hidden md:table-cell">{{ __('Refresh') }}</th>
                        <th class="px-4 py-2 text-left hidden lg:table-cell">{{ __('Last fetched') }}</th>
                        <th class="px-4 py-2 text-center">{{ __('Active') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($sources as $s)
                        <tr wire:key="source-{{ $s->id }}">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">{{ $s->label }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 font-mono text-xs hidden sm:table-cell max-w-[200px] truncate">
                                {{ $s->spreadsheet_id }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 hidden md:table-cell whitespace-nowrap">
                                {{ $refreshOptions[$s->refresh_hours] ?? $s->refresh_hours.' h' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 hidden lg:table-cell whitespace-nowrap">
                                {{ $s->last_fetched_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" wire:click="toggleActive({{ $s->id }})"
                                        title="{{ $s->is_active ? __('Click to deactivate') : __('Click to activate') }}"
                                        class="inline-flex items-center justify-center w-8 h-5 rounded-full transition-colors {{ $s->is_active ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600' }}">
                                    <span class="block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform {{ $s->is_active ? 'translate-x-1.5' : '-translate-x-1.5' }}"></span>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <button type="button"
                                            wire:click="fetchNow({{ $s->id }})"
                                            wire:loading.attr="disabled"
                                            title="{{ __('Fetch now') }}"
                                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors disabled:opacity-50">
                                        {{ __('Fetch') }}
                                    </button>
                                    <button type="button"
                                            wire:click="editSource({{ $s->id }})"
                                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                                        {{ __('Edit') }}
                                    </button>
                                    <button type="button"
                                            wire:click="deleteSource({{ $s->id }})"
                                            wire:confirm="{{ __('Delete this sheet source? This cannot be undone.') }}"
                                            class="text-xs text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 transition-colors">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                {{ __('No sheet sources yet. Click "Add sheet" to configure your first one.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Recent imports --}}
        @if($recentImports->isNotEmpty())
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">{{ __('Recent imports') }}</h2>
                    <form method="POST" action="{{ route('imports.google-sheets.imports.destroy-all') }}"
                          onsubmit="return confirm('{{ __('Delete ALL Google Sheets imports and every lead they created? This cannot be undone.') }}')">
                        @csrf
                        <button type="submit"
                                class="text-xs text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 transition-colors">
                            {{ __('Delete all imports') }}
                        </button>
                    </form>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('When') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Label') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Imported') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Skipped') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Dup.') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Invalid') }}</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($recentImports as $imp)
                                <tr wire:key="imp-{{ $imp->id }}">
                                    <td class="px-4 py-2 text-slate-500 dark:text-slate-400 whitespace-nowrap align-top">{{ $imp->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-2 text-slate-700 dark:text-slate-300 align-top">
                                        {{ $imp->label }}
                                        @if($imp->failed())
                                            <span class="ml-2 inline-flex items-center rounded-full bg-rose-100 dark:bg-rose-950/50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:text-rose-400">{{ __('Failed') }}</span>
                                            <div class="mt-0.5 max-w-md truncate text-xs text-rose-600 dark:text-rose-400" title="{{ $imp->error }}">{{ $imp->error }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right dark:text-slate-300 align-top">{{ $imp->failed() ? '—' : $imp->rows_imported }}</td>
                                    <td class="px-4 py-2 text-right dark:text-slate-300 align-top">{{ $imp->failed() ? '—' : $imp->rows_skipped }}</td>
                                    <td class="px-4 py-2 text-right dark:text-slate-300 align-top">{{ $imp->failed() ? '—' : $imp->rows_duplicate }}</td>
                                    <td class="px-4 py-2 text-right dark:text-slate-300 align-top">{{ $imp->failed() ? '—' : $imp->rows_invalid }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST" action="{{ route('imports.google-sheets.imports.destroy', $imp->id) }}"
                                              onsubmit="return confirm('{{ __('Delete this import and all the leads it created? This cannot be undone.') }}')">
                                            @csrf
                                            <button type="submit"
                                                    class="text-xs text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 transition-colors">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    {{-- ── FORM MODE ───────────────────────────────────────────────────────── --}}
    @else
        <div class="flex items-center gap-3">
            <button type="button" wire:click="backToList"
                    class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors inline-flex items-center gap-1">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4"><path d="M10 3 5 8l5 5"/></svg>
                {{ __('Back') }}
            </button>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">
                {{ $editingId ? __('Edit sheet source') : __('Add sheet source') }}
            </h1>
        </div>

        <div class="space-y-4">
            {{-- Basic details --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Sheet details') }}</h2>

                <div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Label') }}</label>
                    <input wire:model="form.label" type="text"
                           placeholder="{{ __('e.g. Contact form Q1 2026') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('form.label') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Spreadsheet URL or ID') }}</label>
                        <input wire:model="form.spreadsheet_id" type="text"
                               placeholder="https://docs.google.com/spreadsheets/d/…/edit"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('Paste the full URL or just the ID — both work.') }}</p>
                        @error('form.spreadsheet_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Sheet range') }}</label>
                        <input wire:model="form.sheet_range" type="text"
                               placeholder="Sheet1"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('e.g. Sheet1, Sheet1!A:F, Leads!A1:Z500') }}</p>
                        @error('form.sheet_range') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="flex items-center gap-3">
                        <input wire:model="form.has_header_row" type="checkbox" id="has_header_row"
                               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <label for="has_header_row" class="text-sm text-slate-700 dark:text-slate-300">{{ __('First row is header') }}</label>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Refresh interval') }}</label>
                        <select wire:model="form.refresh_hours"
                                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach($refreshOptions as $hours => $label)
                                <option value="{{ $hours }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('form.refresh_hours') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-3 sm:pt-5">
                        <input wire:model="form.is_active" type="checkbox" id="is_active"
                               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <label for="is_active" class="text-sm text-slate-700 dark:text-slate-300">{{ __('Active') }}</label>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ __('Default client') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span>
                        </label>
                        <input wire:model="form.default_client_name" type="text"
                               placeholder="{{ __('Used when no client column is mapped') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.default_client_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ __('Default campaign') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span>
                        </label>
                        <input wire:model="form.default_campaign_name" type="text"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.default_campaign_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Column mapping --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Column mapping') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Load columns from your sheet. Headers are auto-mapped where recognised — review and adjust as needed.') }}
                        </p>
                    </div>
                    <button type="button"
                            wire:click="loadColumns"
                            wire:loading.attr="disabled"
                            class="rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-50 shadow-sm">
                        <span wire:loading.remove wire:target="loadColumns">{{ __('Load columns') }}</span>
                        <span wire:loading wire:target="loadColumns">{{ __('Loading…') }}</span>
                    </button>
                </div>

                @if($loadError)
                    <div class="rounded-lg border border-rose-200 dark:border-rose-800/50 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-sm text-rose-800 dark:text-rose-300">
                        {{ $loadError }}
                    </div>
                @endif

                @if($columnsLoaded && count($detectedColumns) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-xs text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="py-1.5 pr-4 text-left font-medium">{{ __('Sheet column') }}</th>
                                    <th class="py-1.5 text-left font-medium">{{ __('Map to lead field') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($detectedColumns as $i => $col)
                                    <tr wire:key="col-{{ $i }}">
                                        <td class="py-2 pr-4 text-slate-700 dark:text-slate-300 font-medium">
                                            {{ $col['display'] }}
                                        </td>
                                        <td class="py-2 space-y-1.5">
                                            <select wire:model.live="detectedColumns.{{ $i }}.field"
                                                    class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="">— {{ __('skip') }} —</option>
                                                @foreach($leadFields as $key => $fieldLabel)
                                                    <option value="{{ $key }}">{{ $fieldLabel }}</option>
                                                @endforeach
                                            </select>
                                            @if(($col['field'] ?? '') === 'custom_answer')
                                                <input type="text"
                                                       wire:model="detectedColumns.{{ $i }}.custom_key"
                                                       placeholder="{{ __('key name, e.g. event_size') }}"
                                                       class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                                                <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('Stored as this key inside custom answers.') }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif(!$columnsLoaded && !$loadError)
                    <p class="text-xs text-slate-400 dark:text-slate-500 italic">
                        {{ __('Fill in the spreadsheet ID and range above, then click "Load columns" to detect headers.') }}
                    </p>
                @endif
            </div>

            {{-- Save / cancel --}}
            <div class="flex items-center justify-between">
                <button type="button" wire:click="backToList"
                        class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                    {{ __('Cancel') }}
                </button>
                <button type="button" wire:click="saveSource"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-50 shadow-sm">
                    <span wire:loading.remove wire:target="saveSource">{{ $editingId ? __('Save changes') : __('Create source') }}</span>
                    <span wire:loading wire:target="saveSource">{{ __('Saving…') }}</span>
                </button>
            </div>
        </div>
    @endif

</div>
