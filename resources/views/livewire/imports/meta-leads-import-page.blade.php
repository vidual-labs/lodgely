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
                <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Meta Lead Ads (API)') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Pull leads straight from Meta Lead Ads via the API — no Google Sheets in between. Uses the Meta connection from Ad platforms.') }}
                </p>
            </div>
            <button type="button" wire:click="openCreate"
                    class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                {{ __('Add connection') }}
            </button>
        </div>

        @unless($metaConnected)
            <div class="rounded-lg border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                {{ __('Meta is not connected yet.') }}
                <a href="{{ $adPlatformsUrl }}" class="font-medium underline hover:no-underline">{{ __('Connect Meta under Settings → Ad platforms') }}</a>
                {{ __('first — the access token must include the leads_retrieval permission and access to the page that owns your lead forms.') }}
            </div>
        @endunless

        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 text-left">{{ __('Label') }}</th>
                        <th class="px-4 py-2 text-left hidden sm:table-cell">{{ __('Page / Form') }}</th>
                        <th class="px-4 py-2 text-left hidden md:table-cell">{{ __('Refresh') }}</th>
                        <th class="px-4 py-2 text-left hidden lg:table-cell">{{ __('Last fetched') }}</th>
                        <th class="px-4 py-2 text-center">{{ __('Active') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($sources as $s)
                        <tr wire:key="meta-source-{{ $s->id }}">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">{{ $s->label }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 font-mono text-xs hidden sm:table-cell max-w-[200px] truncate">
                                {{ $s->form_id ? __('Form :id', ['id' => $s->form_id]) : __('Page :id', ['id' => $s->page_id]) }}
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
                                            wire:target="fetchNow({{ $s->id }})"
                                            title="{{ __('Fetch now') }}"
                                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors disabled:opacity-50">
                                        <span wire:loading.remove wire:target="fetchNow({{ $s->id }})">{{ __('Fetch') }}</span>
                                        <span wire:loading wire:target="fetchNow({{ $s->id }})">{{ __('Fetching…') }}</span>
                                    </button>
                                    <button type="button"
                                            wire:click="editSource({{ $s->id }})"
                                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                                        {{ __('Edit') }}
                                    </button>
                                    <button type="button"
                                            wire:click="deleteSource({{ $s->id }})"
                                            wire:confirm="{{ __('Delete this Meta Lead Ads source? This cannot be undone.') }}"
                                            class="text-xs text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 transition-colors">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                {{ __('No connections yet. Click "Add connection" to pull leads from Meta Lead Ads.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Recent imports — delete via native POST forms (Livewire wire:click on
             these rows silently drops in production; see CLAUDE.md). --}}
        @if($recentImports->isNotEmpty())
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">{{ __('Recent imports') }}</h2>
                    <form method="POST" action="{{ route('imports.meta-leads.imports.destroy-all') }}"
                          onsubmit="return confirm('{{ __('Delete ALL Meta Lead Ads imports and every lead they created? This cannot be undone.') }}')">
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
                                <tr wire:key="meta-imp-{{ $imp->id }}">
                                    <td class="px-4 py-2 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $imp->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ $imp->label }}</td>
                                    <td class="px-4 py-2 text-right dark:text-slate-300">{{ $imp->rows_imported }}</td>
                                    <td class="px-4 py-2 text-right dark:text-slate-300">{{ $imp->rows_skipped }}</td>
                                    <td class="px-4 py-2 text-right dark:text-slate-300">{{ $imp->rows_duplicate }}</td>
                                    <td class="px-4 py-2 text-right dark:text-slate-300">{{ $imp->rows_invalid }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST" action="{{ route('imports.meta-leads.imports.destroy', $imp->id) }}"
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
                {{ $editingId ? __('Edit Meta Lead Ads source') : __('Add Meta Lead Ads source') }}
            </h1>
        </div>

        <div class="space-y-4">
            {{-- Connection details --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Connection') }}</h2>

                <div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Label') }}</label>
                    <input wire:model="form.label" type="text"
                           placeholder="{{ __('e.g. Spring lead forms') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('form.label') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Facebook Page ID') }}</label>
                        <input wire:model="form.page_id" type="text"
                               placeholder="123456789012345"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('All active lead forms on this page are pulled, unless you pin one below.') }}</p>
                        @error('form.page_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ __('Form ID') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span>
                        </label>
                        <input wire:model="form.form_id" type="text"
                               placeholder="{{ __('Pin a single lead form') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
                        @error('form.form_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Load forms helper — validates the token + page access. --}}
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800 pt-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Load the lead forms on this page to confirm the connection works and optionally pick one.') }}
                    </p>
                    <button type="button"
                            wire:click="loadForms"
                            wire:loading.attr="disabled"
                            wire:target="loadForms"
                            class="shrink-0 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-50 shadow-sm">
                        <span wire:loading.remove wire:target="loadForms">{{ __('Load forms') }}</span>
                        <span wire:loading wire:target="loadForms">{{ __('Loading…') }}</span>
                    </button>
                </div>

                @if($loadError)
                    <div class="rounded-lg border border-rose-200 dark:border-rose-800/50 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-sm text-rose-800 dark:text-rose-300">
                        {{ $loadError }}
                    </div>
                @endif

                @if($formsLoaded)
                    @if(count($availableForms) > 0)
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($availableForms as $f)
                                <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                    <div class="min-w-0">
                                        <div class="text-slate-700 dark:text-slate-200 truncate">{{ $f['name'] ?? __('(unnamed form)') }}</div>
                                        <div class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ $f['id'] }}{{ $f['status'] ? ' · '.$f['status'] : '' }}</div>
                                    </div>
                                    <button type="button"
                                            wire:click="pinForm('{{ $f['id'] }}')"
                                            class="shrink-0 text-xs text-brand-600 dark:text-brand-400 hover:underline">
                                        {{ (string) $form['form_id'] === (string) $f['id'] ? __('Pinned') : __('Pin this form') }}
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-400 dark:text-slate-500 italic">{{ __('No lead forms found on this page.') }}</p>
                    @endif
                @endif
            </div>

            {{-- Defaults & schedule --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Defaults & schedule') }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ __('Default client') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span>
                        </label>
                        <input wire:model="form.default_client_name" type="text"
                               placeholder="{{ __('Attributed to every lead from this connection') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.default_client_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ __('Default campaign') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span>
                        </label>
                        <input wire:model="form.default_campaign_name" type="text"
                               placeholder="{{ __('Used when Meta sends no campaign name') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.default_campaign_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Look back') }}</label>
                        <select wire:model="form.lookback_days"
                                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach($lookbackOptions as $days => $label)
                                <option value="{{ $days }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('How far back each fetch reaches.') }}</p>
                        @error('form.lookback_days') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
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
                        <input wire:model="form.is_active" type="checkbox" id="meta_is_active"
                               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <label for="meta_is_active" class="text-sm text-slate-700 dark:text-slate-300">{{ __('Active') }}</label>
                    </div>
                </div>
            </div>

            {{-- Save / cancel --}}
            <div class="flex items-center justify-between">
                <button type="button" wire:click="backToList"
                        class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                    {{ __('Cancel') }}
                </button>
                <button type="button" wire:click="saveSource"
                        wire:loading.attr="disabled"
                        wire:target="saveSource"
                        class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-50 shadow-sm">
                    <span wire:loading.remove wire:target="saveSource">{{ $editingId ? __('Save changes') : __('Create connection') }}</span>
                    <span wire:loading wire:target="saveSource">{{ __('Saving…') }}</span>
                </button>
            </div>
        </div>
    @endif

</div>
