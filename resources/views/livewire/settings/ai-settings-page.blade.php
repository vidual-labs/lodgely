<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('AI settings') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Configure the language-model provider used for AI summaries, lead qualification and automatic lead ranking. Nothing is sent to a model until you enable AI here.') }}
        </p>
    </div>

    @if(session('status'))
        <div class="rounded-lg px-3 py-2 text-sm bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50">
            {{ session('status') }}
        </div>
    @endif

    @if($testResult)
        @php
            [$tone, $msg] = explode(':', $testResult, 2);
        @endphp
        <div class="rounded-lg px-3 py-2 text-sm
            {{ $tone === 'success'
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50'
                : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50' }}">
            {{ $msg }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-5 rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm">

        {{-- Master enabled toggle --}}
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" wire:model="form.enabled"
                   class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span>
                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Enable AI for this tenant') }}</span>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('When off, all AI buttons are hidden and no jobs run. Setting LODGELY_AI_ENABLED=false in .env removes the AI pages entirely.') }}
                </p>
            </span>
        </label>

        {{-- Provider --}}
        <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Provider') }}</label>
            <select wire:model.live="form.provider"
                    class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('— Choose a provider —') }}</option>
                @foreach($providerOptions as $opt)
                    <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </select>
            @error('form.provider') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Base URL --}}
        <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Base URL') }}</label>
            <input type="text" wire:model="form.base_url"
                   placeholder="{{ $form['provider'] ? ($defaultUrls[$form['provider']] ?? '') : __('Leave blank to use the provider default') }}"
                   class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Examples: https://api.openai.com/v1, https://api.together.xyz/v1, http://localhost:11434.') }}
            </p>
            @error('form.base_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            @if (\App\Rules\HttpUrl::isCleartextToRemoteHost($form['base_url'] ?? null))
                <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                    {{ __('This is a plain http:// address. Your API key is sent to it as a Bearer token, unencrypted. Use https:// unless the host is on your local network.') }}
                </p>
            @endif
        </div>

        {{-- API key --}}
        <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('API key') }}</label>
            <input type="password" wire:model="form.api_key" autocomplete="new-password"
                   placeholder="{{ $form['has_existing_key'] ? __('•••• stored — leave blank to keep') : __('Paste your key here') }}"
                   class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
            <div class="mt-1 flex items-center justify-between">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Stored encrypted on disk. Never displayed again after saving.') }}
                </p>
                @if($form['has_existing_key'])
                    <button type="button" wire:click="clearApiKey"
                            class="text-xs text-rose-600 dark:text-rose-400 hover:underline">{{ __('Clear key') }}</button>
                @endif
            </div>
            @error('form.api_key') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Model --}}
        <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Model') }}</label>
            <input type="text" wire:model="form.model"
                   placeholder="{{ $form['provider'] ? ($defaultModels[$form['provider']] ?? '') : __('e.g. gpt-4o-mini, llama3.1') }}"
                   class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
            @error('form.model') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- House style --}}
        <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                {{ __('House style — what is important?') }}
            </label>
            <textarea wire:model="form.house_style" rows="4" maxlength="2000"
                      placeholder="{{ __('Free-text guidance the AI will read on every call. Example: "Always call out cost-per-lead spikes above 20%. Prefer plain English. Mention organic vs paid mix."') }}"
                      class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            @error('form.house_style') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Temperature --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Temperature') }}</label>
                <input type="number" step="0.1" min="0" max="2" wire:model="form.temperature"
                       placeholder="{{ __('Provider default') }}"
                       class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Optional. 0 = deterministic, 1 = balanced.') }}</p>
            </div>
        </div>

        {{-- Kinds enabled --}}
        <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Enabled AI tasks') }}</label>
            <div class="space-y-1.5">
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2.5 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    <input type="checkbox" wire:model="form.kinds_enabled.report_view"
                           class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <div class="flex-1">
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Report-view summaries') }}</span>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('AI writes a narrative and evaluation on a reporting view for a date range. Aggregate data only — no PII leaves the server.') }}
                        </p>
                    </div>
                </label>
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2.5 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    <input type="checkbox" wire:model="form.kinds_enabled.lead_qualification"
                           class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <div class="flex-1">
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Lead qualification') }}</span>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('AI suggests a priority and reasoning per lead. Pseudonymized PII (masked name / email / phone) is sent to the model. Requires the data-sharing consent below.') }}
                        </p>
                    </div>
                </label>
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2.5 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    <input type="checkbox" wire:model.live="form.kinds_enabled.lead_ranking"
                           class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <div class="flex-1">
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Automatic lead ranking') }}</span>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Every hour the AI ranks new leads against the ranking profiles below and writes the priority, a one-line reason and a few tags straight onto the lead (marked with a sparkle). Anyone who changes the priority overrides it. Pseudonymized lead data is sent to the model; requires the data-sharing consent below.') }}
                        </p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Automatic lead ranking --}}
        <div class="space-y-4 rounded-lg border border-violet-200 dark:border-violet-800/50 bg-violet-50/40 dark:bg-violet-950/20 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Automatic lead ranking') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('The master prompt is built in. Steer it with a short description of your ideal customer; client users can add their own on their profile page.') }}
                </p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('Ranking profile — what is an ideal customer?') }}
                </label>
                <textarea wire:model="form.lead_ranking_profile" rows="4" maxlength="{{ \App\Models\ClientAiProfile::MAX_LENGTH }}"
                          placeholder="{{ __('Example: "Our ideal customer wants to host a dinner for 20–30 people, has a budget of at least 40 EUR per person, and is in Leipzig or Halle. Requests from outside Saxony / Saxony-Anhalt are low priority."') }}"
                          class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                @error('form.lead_ranking_profile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Leads per hourly run') }}</label>
                    <input type="number" min="1" max="200" step="1" wire:model="form.ranking_batch_size"
                           class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Each lead is one model call; the daily cap (LODGELY_AI_MAX_CALLS_PER_DAY) is shared with summaries.') }}</p>
                    @error('form.ranking_batch_size') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Consent --}}
        <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-amber-200 dark:border-amber-800/50 bg-amber-50/60 dark:bg-amber-950/30 px-3 py-2.5">
            <input type="checkbox" wire:model="form.lead_data_consent"
                   class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
            <span>
                <span class="text-sm font-medium text-amber-900 dark:text-amber-200">{{ __('I consent to sending pseudonymized lead data to the chosen provider') }}</span>
                <p class="text-xs text-amber-800/80 dark:text-amber-300/80 mt-0.5">
                    {{ __('Required before lead qualification can run. Names become "Lead #N"; email and phone are masked. Free-text fields (the lead\'s message, campaign labels) are passed through so the model can reason about quality.') }}
                </p>
            </span>
        </label>

        <div class="flex justify-between gap-2 border-t border-slate-100 dark:border-slate-800 pt-4">
            <button type="button" wire:click="testConnection"
                    class="rounded-lg px-3 py-1.5 text-sm border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                {{ __('Test connection') }}
            </button>
            <button type="submit"
                    class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                {{ __('Save settings') }}
            </button>
        </div>
    </form>

    {{-- "Rank now" is a native POST → controller → redirect (see CLAUDE.md on
         morph-dropped clicks) and must sit OUTSIDE the Livewire form above —
         nested forms are invalid HTML. --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Rank unranked leads now') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Runs the hourly sweep immediately: queues the oldest unranked leads (up to the batch size and what is left of today\'s cap). Save your settings first.') }}
            </p>
        </div>
        <form method="POST" action="{{ route('settings.ai.rank-leads') }}"
              onsubmit="this.querySelector('button').disabled = true;">
            @csrf
            <button type="submit"
                    class="rounded-lg px-3 py-1.5 text-sm border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                {{ __('Rank unranked leads now') }}
            </button>
        </form>
    </div>

    {{-- Per-client ranking profiles --}}
    <form wire:submit.prevent="saveClientProfiles" class="space-y-4 rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Per-client ranking profiles') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('What each client considers an ideal customer. Read on top of the profile above when one of their leads is ranked; where the two disagree, the client profile wins. Client users can edit their own on their profile page.') }}
            </p>
        </div>

        @if($clientProfiles === [])
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('No client names yet — profiles appear here once leads or client users carry a client name.') }}</p>
        @else
            <div class="space-y-3">
                @foreach($clientProfiles as $i => $row)
                    <div wire:key="client-profile-{{ $i }}">
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $row['client_name'] }}</label>
                        <textarea wire:model="clientProfiles.{{ $i }}.profile" rows="2" maxlength="{{ \App\Models\ClientAiProfile::MAX_LENGTH }}"
                                  placeholder="{{ __('Describe this client\'s ideal customer — leave blank to use only the profile above.') }}"
                                  class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                        @error('clientProfiles.'.$i.'.profile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end border-t border-slate-100 dark:border-slate-800 pt-4">
                <button type="submit"
                        class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                    {{ __('Save client profiles') }}
                </button>
            </div>
        @endif
    </form>
</div>
