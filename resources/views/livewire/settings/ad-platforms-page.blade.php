@php
    $inputClass = 'block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500';
    $labelClass = 'block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1';
    $extLink = 'inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline';
@endphp

<div class="space-y-6 max-w-3xl" x-data>
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Ad platforms') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Connect Meta Ads and Google Ads so lodgely can pull campaign KPIs into your reports. Credentials are stored encrypted — no .env editing needed.') }}
        </p>
    </div>

    {{-- HTTPS warning (Google OAuth requires it) --}}
    @if(!$appUrlIsHttps)
        <div class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200 space-y-1">
            <p class="font-medium">{{ __('APP_URL is not HTTPS') }}</p>
            <p>{{ __('Google requires the redirect URI to use HTTPS for non-localhost apps. Set APP_URL in .env to your public HTTPS address before connecting Google Ads.') }}</p>
        </div>
    @endif

    {{-- OAuth redirect flashes --}}
    @if($oauthSuccess)
        <div class="rounded-lg px-3 py-2 text-sm bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50">{{ $oauthSuccess }}</div>
    @endif
    @if($oauthError)
        <div class="rounded-lg px-3 py-2 text-sm bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50">{{ $oauthError }}</div>
    @endif

    <form wire:submit.prevent="save" class="space-y-6">

        {{-- ============ META ADS ============ --}}
        <section class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 shadow-sm divide-y divide-slate-100 dark:divide-slate-800">
            <div class="px-5 py-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Meta Ads') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Facebook & Instagram campaign metrics via the Marketing API.') }}</p>
                </div>
                @if($isMetaConnected)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-3 py-1 text-xs font-medium text-emerald-800 dark:text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('Configured') }}</span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-medium text-slate-600 dark:text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>{{ __('Not configured') }}</span>
                @endif
            </div>

            <details class="px-5 py-3 text-sm group">
                <summary class="cursor-pointer font-medium text-slate-700 dark:text-slate-300 select-none">{{ __('How to get these values') }}</summary>

                <div class="mt-3 rounded-lg border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-950/30 px-3 py-2 text-xs text-amber-900 dark:text-amber-200">
                    {{ __('Do the steps in this order. The "Generate token" button stays greyed out until an App is assigned to your System User — and you must be an Admin of the Business to add an App.') }}
                </div>

                <ol class="mt-3 space-y-2 text-slate-500 dark:text-slate-400 list-decimal pl-5">
                    <li>{{ __('Create a Meta app first — apps are made in the Developers portal, not Business Manager. Go to developers.facebook.com → My Apps → Create App, pick type "Business", and attach it to your Business portfolio. Then Add product → Marketing API → Set up.') }}</li>
                    <li>{{ __('Link the app to your Business: Business Settings → Accounts → Apps. If it is not listed, click Add → Connect an App ID and paste the App ID.') }}</li>
                    <li>{{ __('Create or open a System User: Business Settings → Users → System Users. Click Add assets and, under the Apps tab, assign your app with Manage app / Full control. Save.') }}</li>
                    <li>{{ __('Still in Add assets, open the Ad accounts tab and assign your ad account with at least View performance.') }}</li>
                    <li>{{ __('Now "Generate new token" is enabled. Pick your app, tick the ads_read scope (and read_insights if shown), generate, and copy the token immediately — it is only shown once and does not expire.') }}</li>
                    <li>{{ __('Copy your Ad account ID from Business Settings → Accounts → Ad accounts (the number, with or without the act_ prefix). Paste both below, set the currency to match the account, then Save and Test.') }}</li>
                </ol>

                <div class="mt-3 flex flex-wrap gap-3">
                    <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener noreferrer" class="{{ $extLink }}">
                        {{ __('Create a Meta app') }}
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 9.5 9.5 2.5M5 2.5h4.5v4.5"/></svg>
                    </a>
                    <a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener noreferrer" class="{{ $extLink }}">
                        {{ __('Open System Users') }}
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 9.5 9.5 2.5M5 2.5h4.5v4.5"/></svg>
                    </a>
                </div>
            </details>

            <div class="px-5 py-4 space-y-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="form.meta_enabled" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Pull live Meta Ads metrics on the daily schedule') }}</span>
                </label>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">{{ __('Ad account ID') }}</label>
                        <input type="text" wire:model="form.meta_ad_account_id" autocomplete="off" placeholder="1234567890" class="{{ $inputClass }}">
                        @error('form.meta_ad_account_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">{{ __('Currency') }}</label>
                        <input type="text" wire:model="form.meta_currency" autocomplete="off" placeholder="USD" maxlength="8" class="{{ $inputClass }}">
                        @error('form.meta_currency') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">{{ __('Access token') }}</label>
                    <input type="password" wire:model="form.meta_access_token" autocomplete="new-password"
                           placeholder="{{ $form['has_meta_token'] ? __('•••• stored — leave blank to keep') : __('Paste your long-lived access token') }}"
                           class="{{ $inputClass }}">
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Stored encrypted at rest. We only read aggregate campaign metrics — never personal data.') }}</p>
                    @error('form.meta_access_token') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if($metaTestResult)
                    @php [$tone, $msg] = explode(':', $metaTestResult, 2); @endphp
                    <div class="rounded-lg px-3 py-2 text-sm {{ $tone === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50' }}">{{ $msg }}</div>
                @endif

                @if($isMetaConnected)
                    <button type="button" wire:click="testMeta"
                            class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        {{ __('Test connection') }}
                    </button>
                @endif
            </div>
        </section>

        {{-- ============ GOOGLE ADS ============ --}}
        <section class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 shadow-sm divide-y divide-slate-100 dark:divide-slate-800">
            <div class="px-5 py-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Google Ads') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Campaign metrics via the Google Ads REST API. Click "Connect" and we capture the refresh token for you — no scripts.') }}</p>
                </div>
                @if($isGoogleConnected)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-3 py-1 text-xs font-medium text-emerald-800 dark:text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('Connected') }}</span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-medium text-slate-600 dark:text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>{{ __('Not connected') }}</span>
                @endif
            </div>

            <details class="px-5 py-3 text-sm group">
                <summary class="cursor-pointer font-medium text-slate-700 dark:text-slate-300 select-none">{{ __('How to get these values') }}</summary>
                <ol class="mt-3 space-y-2 text-slate-500 dark:text-slate-400 list-decimal pl-5">
                    <li>{{ __('Apply for a Google Ads API developer token (Google Ads → Tools → API Center). Basic access is enough to read metrics.') }}</li>
                    <li>{{ __('In Google Cloud Console, enable the Google Ads API and create an OAuth 2.0 "Web application" client. Add the redirect URI shown below.') }}</li>
                    <li>{{ __('Copy the Customer ID from the top-right of your Google Ads account (digits only). If you log in through a manager account, also set the Login customer (MCC) ID.') }}</li>
                    <li>{{ __('Save the fields below, then click "Connect Google Ads" to authorize and capture the refresh token automatically.') }}</li>
                </ol>
                <div class="mt-3 flex flex-wrap gap-3">
                    <a href="https://console.cloud.google.com/apis/library/googleads.googleapis.com" target="_blank" rel="noopener noreferrer" class="{{ $extLink }}">
                        {{ __('Enable Google Ads API') }}
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 9.5 9.5 2.5M5 2.5h4.5v4.5"/></svg>
                    </a>
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer" class="{{ $extLink }}">
                        {{ __('Create OAuth client') }}
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 9.5 9.5 2.5M5 2.5h4.5v4.5"/></svg>
                    </a>
                </div>

                {{-- Redirect URI copy box --}}
                <div class="mt-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">{{ __('Authorized redirect URI (paste into your OAuth client):') }}</p>
                    <div x-data="{ copied: false }" class="flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/50 px-3 py-2">
                        <code class="flex-1 font-mono text-xs text-slate-800 dark:text-slate-200 break-all select-all">{{ $googleRedirectUri }}</code>
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $googleRedirectUri }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="shrink-0 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1 text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <span x-show="!copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak class="text-emerald-600 dark:text-emerald-400">{{ __('Copied!') }}</span>
                        </button>
                    </div>
                </div>
            </details>

            <div class="px-5 py-4 space-y-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="form.google_enabled" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Pull live Google Ads metrics on the daily schedule') }}</span>
                </label>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">{{ __('Customer ID') }}</label>
                        <input type="text" wire:model="form.google_customer_id" autocomplete="off" placeholder="1234567890" class="{{ $inputClass }}">
                        @error('form.google_customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">{{ __('Login customer (MCC) ID') }} <span class="text-slate-400 font-normal">{{ __('— optional') }}</span></label>
                        <input type="text" wire:model="form.google_login_customer_id" autocomplete="off" placeholder="{{ __('Manager account, if any') }}" class="{{ $inputClass }}">
                        @error('form.google_login_customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">{{ __('Developer token') }}</label>
                    <input type="password" wire:model="form.google_developer_token" autocomplete="new-password"
                           placeholder="{{ $form['has_google_developer'] ? __('•••• stored — leave blank to keep') : __('Paste your developer token') }}"
                           class="{{ $inputClass }}">
                    @error('form.google_developer_token') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">{{ __('OAuth client ID') }}</label>
                        <input type="text" wire:model="form.google_client_id" autocomplete="off" placeholder="123-abc.apps.googleusercontent.com" class="{{ $inputClass }}">
                        @error('form.google_client_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">{{ __('OAuth client secret') }}</label>
                        <input type="password" wire:model="form.google_client_secret" autocomplete="new-password"
                               placeholder="{{ $form['has_google_secret'] ? __('•••• stored — leave blank to keep') : __('Paste your client secret') }}"
                               class="{{ $inputClass }}">
                        @error('form.google_client_secret') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Secrets are stored encrypted. Changing the client ID or secret clears the captured refresh token — reconnect afterwards.') }}</p>

                @if($form['has_google_refresh'])
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">{{ __('✓ Refresh token captured. lodgely can fetch Google Ads metrics.') }}</p>
                @endif

                @if($googleTestResult)
                    @php [$gtone, $gmsg] = explode(':', $googleTestResult, 2); @endphp
                    <div class="rounded-lg px-3 py-2 text-sm {{ $gtone === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50' }}">{{ $gmsg }}</div>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    @if($form['google_client_id'] && $form['has_google_secret'])
                        <a href="{{ $googleConnectUrl }}"
                           class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                            {{ $form['has_google_refresh'] ? __('Reconnect Google Ads') : __('Connect Google Ads') }}
                        </a>
                    @endif

                    @if($isGoogleConnected)
                        <button type="button" wire:click="testGoogle"
                                class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                            {{ __('Test connection') }}
                        </button>
                        <button type="button" wire:click="disconnectGoogle"
                                wire:confirm="{{ __('Disconnect Google Ads? You will need to authorize again to reconnect.') }}"
                                class="text-sm text-rose-600 dark:text-rose-400 hover:underline">
                            {{ __('Disconnect') }}
                        </button>
                    @endif
                </div>
            </div>
        </section>

        {{-- Save (covers both cards) --}}
        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-slate-900 dark:bg-slate-100 px-4 py-2 text-sm font-medium text-white dark:text-slate-900 hover:opacity-90 transition-opacity">
                {{ __('Save settings') }}
            </button>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Daily pull runs at 05:00. Use') }} <code class="font-mono text-xs">lodgely:import:ad-metrics --days=30</code> {{ __('to backfill.') }}</p>
        </div>
    </form>
</div>
