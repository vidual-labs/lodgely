<x-layouts.app>
    @php
        $inputClass = 'block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500';
        $labelClass = 'block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1';
    @endphp

    <div class="space-y-6 max-w-3xl">
        <div>
            <a href="{{ route('settings.ad-platforms') }}#connectors" class="text-xs font-medium text-slate-500 dark:text-slate-400 hover:underline">
                &larr; {{ __('Back to ad platforms') }}
            </a>
            <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-50">
                {{ __('Connector: :client', ['client' => $connector->client_name]) }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Dedicated Meta / Google Ads credentials for this client. Their ad spend is reported to them alone.') }}
            </p>
        </div>

        @if(!$appUrlIsHttps)
            <div class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
                <p class="font-medium">{{ __('APP_URL is not HTTPS') }}</p>
                <p>{{ __('Google requires the redirect URI to use HTTPS for non-localhost apps.') }}</p>
            </div>
        @endif

        @if(session('connectorNotice'))
            <div class="rounded-lg px-3 py-2 text-sm bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50">{{ session('connectorNotice') }}</div>
        @endif
        @if(session('oauth_success'))
            <div class="rounded-lg px-3 py-2 text-sm bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50">{{ session('oauth_success') }}</div>
        @endif
        @if(session('oauth_error'))
            <div class="rounded-lg px-3 py-2 text-sm bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50">{{ session('oauth_error') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg px-3 py-2 text-sm bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.ad-platforms.connectors.update', $connector) }}" class="space-y-6">
            @csrf

            <div>
                <label class="{{ $labelClass }}">{{ __('Internal label') }} <span class="text-slate-400 font-normal">{{ __('— optional, for your reference only') }}</span></label>
                <input type="text" name="internal_label" value="{{ old('internal_label', $connector->internal_label) }}" autocomplete="off"
                       placeholder="{{ __('e.g. Acme — Brand A') }}" class="{{ $inputClass }} max-w-sm">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Never sent to Meta or Google, never used for matching — purely to help you tell connectors apart in the list.') }}</p>
            </div>

            {{-- ============ META ADS ============ --}}
            <section class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 shadow-sm divide-y divide-slate-100 dark:divide-slate-800">
                <div class="px-5 py-4 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Meta Ads') }}</h2>
                    @if($connector->isMetaConnected())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-3 py-1 text-xs font-medium text-emerald-800 dark:text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('Configured') }}</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-medium text-slate-600 dark:text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>{{ __('Not configured') }}</span>
                    @endif
                </div>

                <div class="px-5 py-4 space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="meta_enabled" value="1" @checked($connector->meta_enabled) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Pull live Meta Ads metrics on the daily schedule') }}</span>
                    </label>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $labelClass }}">{{ __('Ad account ID') }}</label>
                            <input type="text" name="meta_ad_account_id" value="{{ old('meta_ad_account_id', $connector->meta_ad_account_id) }}" autocomplete="off" placeholder="1234567890" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">{{ __('Currency') }}</label>
                            <input type="text" name="meta_currency" value="{{ old('meta_currency', $connector->meta_currency) }}" autocomplete="off" placeholder="USD" maxlength="8" class="{{ $inputClass }}">
                        </div>
                    </div>

                    <div>
                        <label class="{{ $labelClass }}">{{ __('Access token') }}</label>
                        <input type="password" name="meta_access_token" autocomplete="new-password"
                               placeholder="{{ $connector->effectiveMetaAccessToken() !== '' ? __('•••• stored — leave blank to keep') : __('Paste your long-lived access token') }}"
                               class="{{ $inputClass }}">
                    </div>

                    @if(session('connectorMetaTestResult'))
                        @php [$tone, $msg] = explode(':', session('connectorMetaTestResult'), 2); @endphp
                        <div class="rounded-lg px-3 py-2 text-sm {{ $tone === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50' }}">{{ $msg }}</div>
                    @endif

                    @if($connector->isMetaConnected())
                        <button type="submit" form="test-meta-form" class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                            {{ __('Test connection') }}
                        </button>
                    @endif
                </div>

                @if($connector->isMetaConnected())
                    <div class="px-5 py-4 space-y-2 bg-slate-50/50 dark:bg-slate-950/20">
                        <label class="{{ $labelClass }}">{{ __('Brand filter — Facebook Page ID') }} <span class="text-slate-400 font-normal">{{ __('— optional') }}</span></label>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('If this ad account runs more than one business, scope this connector to the ads published as one Facebook Page. Matched by the Page\'s permanent id, never its name.') }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="text" form="meta-page-filter-form" name="meta_page_id" value="{{ old('meta_page_id', $connector->meta_page_id) }}"
                                   autocomplete="off" placeholder="{{ __('e.g. 111905751401508') }}" class="{{ $inputClass }} max-w-xs">
                            <button type="submit" form="meta-page-filter-form" class="shrink-0 rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                {{ __('Resolve & save') }}
                            </button>
                        </div>
                        @if($connector->hasMetaPageFilter())
                            <p class="text-xs text-emerald-700 dark:text-emerald-400">{{ __('✓ Scoped to Page ":name" (id :id). Clear the field and resolve again to cover the whole account.', ['name' => $connector->meta_page_name, 'id' => $connector->meta_page_id]) }}</p>
                        @endif
                    </div>
                @endif
            </section>

            {{-- ============ GOOGLE ADS ============ --}}
            <section class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 shadow-sm divide-y divide-slate-100 dark:divide-slate-800">
                <div class="px-5 py-4 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Google Ads') }}</h2>
                    @if($connector->isGoogleConnected())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-3 py-1 text-xs font-medium text-emerald-800 dark:text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('Connected') }}</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-medium text-slate-600 dark:text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>{{ __('Not connected') }}</span>
                    @endif
                </div>

                <div class="px-5 py-4 space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Authorized redirect URI:') }}
                        <code class="font-mono text-xs break-all">{{ $googleRedirectUri }}</code>
                    </p>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="google_enabled" value="1" @checked($connector->google_enabled) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Pull live Google Ads metrics on the daily schedule') }}</span>
                    </label>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $labelClass }}">{{ __('Customer ID') }}</label>
                            <input type="text" name="google_customer_id" value="{{ old('google_customer_id', $connector->google_customer_id) }}" autocomplete="off" placeholder="1234567890" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">{{ __('Login customer (MCC) ID') }} <span class="text-slate-400 font-normal">{{ __('— optional') }}</span></label>
                            <input type="text" name="google_login_customer_id" value="{{ old('google_login_customer_id', $connector->google_login_customer_id) }}" autocomplete="off" class="{{ $inputClass }}">
                        </div>
                    </div>

                    <div>
                        <label class="{{ $labelClass }}">{{ __('Developer token') }}</label>
                        <input type="password" name="google_developer_token" autocomplete="new-password"
                               placeholder="{{ $connector->effectiveGoogleDeveloperToken() !== '' ? __('•••• stored — leave blank to keep') : __('Paste your developer token') }}"
                               class="{{ $inputClass }}">
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $labelClass }}">{{ __('OAuth client ID') }}</label>
                            <input type="text" name="google_client_id" value="{{ old('google_client_id', $connector->google_client_id) }}" autocomplete="off" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">{{ __('OAuth client secret') }}</label>
                            <input type="password" name="google_client_secret" autocomplete="new-password"
                                   placeholder="{{ $connector->effectiveGoogleClientSecret() !== '' ? __('•••• stored — leave blank to keep') : __('Paste your client secret') }}"
                                   class="{{ $inputClass }}">
                        </div>
                    </div>

                    @if($connector->effectiveGoogleRefreshToken() !== '')
                        <p class="text-xs text-emerald-700 dark:text-emerald-400">{{ __('✓ Refresh token captured.') }}</p>
                    @endif

                    @if(session('connectorGoogleTestResult'))
                        @php [$gtone, $gmsg] = explode(':', session('connectorGoogleTestResult'), 2); @endphp
                        <div class="rounded-lg px-3 py-2 text-sm {{ $gtone === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50' }}">{{ $gmsg }}</div>
                    @endif

                    <div class="flex flex-wrap items-center gap-3">
                        @if($connector->google_client_id && $connector->effectiveGoogleClientSecret() !== '')
                            <a href="{{ $googleConnectUrl }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                {{ $connector->effectiveGoogleRefreshToken() !== '' ? __('Reconnect Google Ads') : __('Connect Google Ads') }}
                            </a>
                        @endif

                        @if($connector->isGoogleConnected())
                            <button type="submit" form="test-google-form" class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                {{ __('Test connection') }}
                            </button>
                            <button type="submit" form="disconnect-google-form"
                                    onclick="return confirm('{{ __('Disconnect Google Ads for this connector?') }}')"
                                    class="text-sm text-rose-600 dark:text-rose-400 hover:underline">
                                {{ __('Disconnect') }}
                            </button>
                        @endif
                    </div>
                </div>

                @if($connector->isGoogleConnected())
                    <div class="px-5 py-4 space-y-2 bg-slate-50/50 dark:bg-slate-950/20">
                        <label class="{{ $labelClass }}">{{ __('Brand filter — Business Name asset ID') }} <span class="text-slate-400 font-normal">{{ __('— optional') }}</span></label>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('If this account runs Performance Max / Demand Gen campaigns for more than one business, scope this connector to campaigns using one Business Name asset. Matched by the asset\'s id, never its text.') }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="text" form="google-business-name-filter-form" name="google_business_name_asset_id" value="{{ old('google_business_name_asset_id', $connector->google_business_name_asset_id) }}"
                                   autocomplete="off" placeholder="{{ __('e.g. 987654321') }}" class="{{ $inputClass }} max-w-xs">
                            <button type="submit" form="google-business-name-filter-form" class="shrink-0 rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                {{ __('Resolve & save') }}
                            </button>
                        </div>
                        @if($connector->hasGoogleBusinessNameFilter())
                            <p class="text-xs text-emerald-700 dark:text-emerald-400">{{ __('✓ Scoped to business name ":name" (id :id). Clear the field and resolve again to cover the whole account.', ['name' => $connector->google_business_name_asset_name, 'id' => $connector->google_business_name_asset_id]) }}</p>
                        @endif
                    </div>
                @endif
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-slate-900 dark:bg-slate-100 px-4 py-2 text-sm font-medium text-white dark:text-slate-900 hover:opacity-90 transition-opacity">
                    {{ __('Save connector') }}
                </button>
            </div>
        </form>

        {{-- Standalone forms for actions outside the main update form (test / disconnect / delete) --}}
        <form id="test-meta-form" method="POST" action="{{ route('settings.ad-platforms.connectors.test', ['connector' => $connector, 'platform' => 'meta']) }}">
            @csrf
        </form>
        <form id="test-google-form" method="POST" action="{{ route('settings.ad-platforms.connectors.test', ['connector' => $connector, 'platform' => 'google']) }}">
            @csrf
        </form>
        <form id="disconnect-google-form" method="POST" action="{{ route('settings.ad-platforms.connectors.google.disconnect', $connector) }}">
            @csrf
        </form>
        <form id="meta-page-filter-form" method="POST" action="{{ route('settings.ad-platforms.connectors.meta.page-filter', $connector) }}">
            @csrf
        </form>
        <form id="google-business-name-filter-form" method="POST" action="{{ route('settings.ad-platforms.connectors.google.business-name-filter', $connector) }}">
            @csrf
        </form>

        <div class="rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/50 dark:bg-rose-950/20 px-5 py-4 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-rose-800 dark:text-rose-300">{{ __('Remove this connector') }}</p>
                <p class="text-xs text-rose-700/80 dark:text-rose-400/80">{{ __('Stops the daily pull for :client. Already-imported ad spend rows are kept.', ['client' => $connector->client_name]) }}</p>
            </div>
            <form method="POST" action="{{ route('settings.ad-platforms.connectors.destroy', $connector) }}"
                  onsubmit="return confirm('{{ __('Remove this connector? Already-imported ad spend rows are kept.') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="shrink-0 rounded-lg border border-rose-300 dark:border-rose-800 px-3 py-1.5 text-xs font-medium text-rose-700 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/40 transition-colors">
                    {{ __('Remove') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
