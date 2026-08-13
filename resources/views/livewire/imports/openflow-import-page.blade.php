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
                <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('OpenFlow') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Pull leads from an OpenFlow form. Each source signs in to your OpenFlow install and fetches new submissions on its own schedule.') }}
                </p>
            </div>
            <button type="button" wire:click="openCreate"
                    class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                {{ __('Add OpenFlow source') }}
            </button>
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 text-left">{{ __('Label') }}</th>
                        <th class="px-4 py-2 text-left hidden sm:table-cell">{{ __('Form') }}</th>
                        <th class="px-4 py-2 text-left hidden md:table-cell">{{ __('Client') }}</th>
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
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 hidden sm:table-cell max-w-[200px] truncate">
                                {{ $s->form_name ?: $s->form_id }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 hidden md:table-cell max-w-[160px] truncate">
                                {{ $s->default_client_name ?: '—' }}
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
                                    {{-- Native POST, not wire:click: the Livewire action silently --}}
                                    {{-- dropped clicks in production (CLAUDE.md morph-drop gotcha), --}}
                                    {{-- so "Fetch" appeared to do nothing. A real form always fires. --}}
                                    <form method="POST" action="{{ route('imports.openflow.fetch', $s->id) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                title="{{ __('Fetch now') }}"
                                                class="text-xs text-slate-500 dark:text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                                            {{ __('Fetch') }}
                                        </button>
                                    </form>
                                    <button type="button"
                                            wire:click="editSource({{ $s->id }})"
                                            class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                                        {{ __('Edit') }}
                                    </button>
                                    <button type="button"
                                            wire:click="deleteSource({{ $s->id }})"
                                            wire:confirm="{{ __('Delete this OpenFlow source? This cannot be undone.') }}"
                                            class="text-xs text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 transition-colors">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                {{ __('No OpenFlow sources yet. Click "Add OpenFlow source" to connect your first form.') }}
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
                    <form method="POST" action="{{ route('imports.openflow.imports.destroy-all') }}"
                          onsubmit="return confirm('{{ __('Delete ALL OpenFlow imports and every lead they created? This cannot be undone.') }}')">
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
                                        <form method="POST" action="{{ route('imports.openflow.imports.destroy', $imp->id) }}"
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
                {{ $editingId ? __('Edit OpenFlow source') : __('Add OpenFlow source') }}
            </h1>
        </div>

        <div class="space-y-4">
            {{-- Connection --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Connection') }}</h2>

                <div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Label') }}</label>
                    <input wire:model="form.label" type="text"
                           placeholder="{{ __('e.g. Acme contact form') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @error('form.label') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('OpenFlow base URL') }}</label>
                    <input wire:model="form.base_url" type="url"
                           placeholder="https://forms.example.com"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('Where your OpenFlow install is reachable — no trailing /api.') }}</p>
                    @error('form.base_url') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    @if (\App\Rules\HttpUrl::isCleartextToRemoteHost($form['base_url'] ?? null))
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            {{ __('This is a plain http:// address. The API token or login password for this source is sent to it on every pull, unencrypted. Use https:// unless the host is on your local network.') }}
                        </p>
                    @endif
                </div>

                {{-- Auth: API token (recommended) --}}
                <div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                        {{ __('API token') }} <span class="text-emerald-600 dark:text-emerald-400">{{ __('(recommended)') }}</span>
                    </label>
                    <input wire:model="form.api_token" type="password" autocomplete="off"
                           placeholder="{{ $editingId ? __('Leave blank to keep current') : 'ofw_…' }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                        {{ __('In OpenFlow, go to Settings → API Tokens and create a read-only token. Stored encrypted. Preferred over a password.') }}
                    </p>
                    @error('form.api_token') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Auth: login fallback --}}
                <div class="rounded-lg border border-slate-200 dark:border-slate-700/60 p-4 space-y-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Or sign in with an OpenFlow login instead of a token:') }}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('OpenFlow login email') }}</label>
                            <input wire:model="form.email" type="email" autocomplete="off"
                                   placeholder="admin@openflow.local"
                                   class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @error('form.email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('OpenFlow password') }}</label>
                            <input wire:model="form.password" type="password" autocomplete="new-password"
                                   placeholder="{{ $editingId ? __('Leave blank to keep current') : '••••••••' }}"
                                   class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('Stored encrypted. Used only if no API token is set.') }}</p>
                            @error('form.password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form selection --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Form') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Sign in to list your OpenFlow forms, then pick the one to pull leads from.') }}
                        </p>
                    </div>
                    <button type="button" wire:click="loadForms" wire:loading.attr="disabled"
                            class="rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-50 shadow-sm">
                        <span wire:loading.remove wire:target="loadForms">{{ __('Load forms') }}</span>
                        <span wire:loading wire:target="loadForms">{{ __('Loading…') }}</span>
                    </button>
                </div>

                @if($loadError)
                    <div class="rounded-lg border border-rose-200 dark:border-rose-800/50 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-sm text-rose-800 dark:text-rose-300">
                        {{ $loadError }}
                    </div>
                @endif

                @if($formsLoaded && count($availableForms) > 0)
                    <div class="space-y-1.5">
                        @foreach($availableForms as $f)
                            <label wire:key="of-form-{{ $f['id'] }}"
                                   class="flex items-center justify-between rounded-lg border px-3 py-2 cursor-pointer transition-colors {{ $form['form_id'] === $f['id'] ? 'border-brand-400 bg-brand-50 dark:bg-brand-950/30 dark:border-brand-600' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ $f['title'] ?: __('Untitled form') }}</div>
                                    <div class="text-xs text-slate-400 dark:text-slate-500 font-mono truncate">{{ $f['id'] }}</div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0 pl-3">
                                    <span class="text-xs text-slate-400 dark:text-slate-500">{{ $f['submission_count'] }} {{ __('subs') }}</span>
                                    <button type="button" wire:click="pinForm('{{ $f['id'] }}')"
                                            class="text-xs font-medium {{ $form['form_id'] === $f['id'] ? 'text-brand-600 dark:text-brand-400' : 'text-slate-500 dark:text-slate-400 hover:text-brand-600' }}">
                                        {{ $form['form_id'] === $f['id'] ? __('Selected') : __('Select') }}
                                    </button>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @elseif($formsLoaded)
                    <p class="text-xs text-slate-400 dark:text-slate-500 italic">{{ __('This account owns no forms.') }}</p>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Form ID') }}</label>
                        <input wire:model="form.form_id" type="text"
                               placeholder="{{ __('Picked above, or paste a form UUID') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
                        @error('form.form_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Form name') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span></label>
                        <input wire:model="form.form_name" type="text"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.form_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Assignment --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Assignment & schedule') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ __('Client') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(who sees these leads)') }}</span>
                        </label>
                        <input wire:model="form.default_client_name" type="text"
                               placeholder="{{ __('Exact client name as used for this customer') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('Used unless a field is mapped to "Client name". Must match the client account scope.') }}</p>
                        @error('form.default_client_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Default campaign') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span></label>
                        <input wire:model="form.default_campaign_name" type="text"
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.default_campaign_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        <input wire:model="form.is_active" type="checkbox" id="of_is_active"
                               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <label for="of_is_active" class="text-sm text-slate-700 dark:text-slate-300">{{ __('Active') }}</label>
                    </div>
                </div>
            </div>

            {{-- Field mapping --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Field mapping') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Map each OpenFlow field to a lead field. Unmapped fields are kept as custom answers.') }}
                        </p>
                    </div>
                    <button type="button" wire:click="loadFields" wire:loading.attr="disabled"
                            class="rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-50 shadow-sm">
                        <span wire:loading.remove wire:target="loadFields">{{ __('Load fields') }}</span>
                        <span wire:loading wire:target="loadFields">{{ __('Loading…') }}</span>
                    </button>
                </div>

                @if($fieldsLoaded && count($mappedFields) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-xs text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="py-1.5 pr-4 text-left font-medium">{{ __('OpenFlow field') }}</th>
                                    <th class="py-1.5 text-left font-medium">{{ __('Map to lead field') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($mappedFields as $i => $row)
                                    <tr wire:key="of-field-{{ $row['id'] }}">
                                        <td class="py-2 pr-4 align-top">
                                            <div class="text-slate-700 dark:text-slate-300 font-medium">{{ $row['label'] }}</div>
                                            @if(!empty($row['type']))
                                                <div class="text-xs text-slate-400 dark:text-slate-500">{{ $row['type'] }}</div>
                                            @endif
                                        </td>
                                        <td class="py-2 space-y-1.5">
                                            <select wire:model.live="mappedFields.{{ $i }}.field"
                                                    class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="">— {{ __('keep as custom answer') }} —</option>
                                                @foreach($leadFields as $key => $fieldLabel)
                                                    <option value="{{ $key }}">{{ $fieldLabel }}</option>
                                                @endforeach
                                            </select>
                                            @if(($row['field'] ?? '') === 'custom_answer')
                                                <input type="text"
                                                       wire:model="mappedFields.{{ $i }}.custom_key"
                                                       placeholder="{{ __('key name, e.g. budget') }}"
                                                       class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-brand-500 focus:ring-brand-500">
                                                <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('Stored as this key inside custom answers.') }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif($fieldsLoaded)
                    <p class="text-xs text-slate-400 dark:text-slate-500 italic">{{ __('This form has no fields to map.') }}</p>
                @else
                    <p class="text-xs text-slate-400 dark:text-slate-500 italic">
                        {{ __('Pick a form above, then click "Load fields" to configure the mapping. You can also save now and rely on automatic field-type matching.') }}
                    </p>
                @endif
            </div>

            {{-- Save / cancel --}}
            <div class="flex items-center justify-between">
                <button type="button" wire:click="backToList"
                        class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                    {{ __('Cancel') }}
                </button>
                <button type="button" wire:click="saveSource" wire:loading.attr="disabled"
                        class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors disabled:opacity-50 shadow-sm">
                    <span wire:loading.remove wire:target="saveSource">{{ $editingId ? __('Save changes') : __('Create source') }}</span>
                    <span wire:loading wire:target="saveSource">{{ __('Saving…') }}</span>
                </button>
            </div>
        </div>
    @endif

</div>
