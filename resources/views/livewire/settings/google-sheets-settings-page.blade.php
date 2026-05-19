<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Google Sheets') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Connect a Google account so lodgely can read data from Google Sheets.') }}
        </p>
    </div>

    {{-- HTTPS warning --}}
    @if(!$appUrlIsHttps)
        <div class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200 space-y-1">
            <p class="font-medium">{{ __('APP_URL is not HTTPS') }}</p>
            <p>{{ __('Google requires the redirect URI to use HTTPS for non-localhost apps. Update APP_URL in .env to your public HTTPS address (e.g. https://lodgely.example.com) before authorizing.') }}</p>
        </div>
    @endif

    {{-- Flash messages from OAuth redirect --}}
    @if($oauthSuccess)
        <div class="rounded-lg px-3 py-2 text-sm bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50">
            {{ $oauthSuccess }}
        </div>
    @endif

    @if($oauthError)
        <div class="rounded-lg px-3 py-2 text-sm bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50">
            {{ $oauthError }}
        </div>
    @endif

    {{-- Test connection result --}}
    @if($testResult)
        @php [$tone, $msg] = explode(':', $testResult, 2); @endphp
        <div class="rounded-lg px-3 py-2 text-sm
            {{ $tone === 'success'
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50'
                : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50' }}">
            {{ $msg }}
        </div>
    @endif

    {{-- Setup guide --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800 shadow-sm">
        <div class="px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Google Cloud Console setup') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Complete these steps once in Google Cloud Console, then paste the credentials below.') }}</p>
        </div>

        <ol class="divide-y divide-slate-100 dark:divide-slate-800">
            {{-- Step 1 --}}
            <li class="px-5 py-4 flex gap-4">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-400">1</span>
                <div class="space-y-1 text-sm">
                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ __('Enable the Google Sheets API') }}</p>
                    <p class="text-slate-500 dark:text-slate-400">{{ __('Open the API Library, search for "Google Sheets API" and click Enable.') }}</p>
                    <a href="https://console.cloud.google.com/apis/library/sheets.googleapis.com"
                       target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                        {{ __('Open Google Sheets API in Cloud Console') }}
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 9.5 9.5 2.5M5 2.5h4.5v4.5"/></svg>
                    </a>
                </div>
            </li>

            {{-- Step 2 --}}
            <li class="px-5 py-4 flex gap-4">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-400">2</span>
                <div class="space-y-1 text-sm">
                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ __('Configure the OAuth consent screen') }}</p>
                    <p class="text-slate-500 dark:text-slate-400">{{ __('If you haven\'t already, set up a consent screen. User type: External is fine for your own account. Add the scope ') }}<code class="font-mono text-xs">.../auth/spreadsheets.readonly</code>{{ __('. You don\'t need to publish it — leave it in Testing and add your Google account as a test user.') }}</p>
                    <a href="https://console.cloud.google.com/apis/credentials/consent"
                       target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                        {{ __('Open OAuth consent screen') }}
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 9.5 9.5 2.5M5 2.5h4.5v4.5"/></svg>
                    </a>
                </div>
            </li>

            {{-- Step 3 --}}
            <li class="px-5 py-4 flex gap-4">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-400">3</span>
                <div class="space-y-1 text-sm">
                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ __('Create an OAuth 2.0 Client ID') }}</p>
                    <p class="text-slate-500 dark:text-slate-400">{{ __('Go to Credentials → Create Credentials → OAuth client ID. Choose ') }}<strong>{{ __('Web application') }}</strong>{{ __('. Under "Authorized redirect URIs" add exactly this URL:') }}</p>
                    <div x-data="{ copied: false }"
                         class="flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/50 px-3 py-2">
                        <code class="flex-1 font-mono text-xs text-slate-800 dark:text-slate-200 break-all select-all">{{ $redirectUri }}</code>
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $redirectUri }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="shrink-0 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1 text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <span x-show="!copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak class="text-emerald-600 dark:text-emerald-400">{{ __('Copied!') }}</span>
                        </button>
                    </div>
                    <a href="https://console.cloud.google.com/apis/credentials"
                       target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                        {{ __('Open Credentials page') }}
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 9.5 9.5 2.5M5 2.5h4.5v4.5"/></svg>
                    </a>
                </div>
            </li>

            {{-- Step 4 --}}
            <li class="px-5 py-4 flex gap-4">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-400">4</span>
                <div class="text-sm">
                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ __('Copy the Client ID and Client secret below, then click "Connect to Google"') }}</p>
                    <p class="mt-0.5 text-slate-500 dark:text-slate-400">{{ __('Google will show both values on the Credentials page after creating the client. Paste them into the fields below.') }}</p>
                </div>
            </li>
        </ol>
    </div>

    {{-- Connection status badge --}}
    <div class="flex items-center gap-2">
        @if($isConnected)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-3 py-1 text-xs font-medium text-emerald-800 dark:text-emerald-300">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ __('Connected') }}
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-medium text-slate-600 dark:text-slate-400">
                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                {{ __('Not connected') }}
            </span>
        @endif
    </div>

    {{-- Credential form --}}
    <form wire:submit.prevent="save"
          class="space-y-5 rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm">

        <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Client ID') }}</label>
            <input type="text" wire:model="form.client_id" autocomplete="off"
                   placeholder="123456789-abc.apps.googleusercontent.com"
                   class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
            @error('form.client_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Client secret') }}</label>
            <input type="password" wire:model="form.client_secret" autocomplete="new-password"
                   placeholder="{{ $form['has_secret'] ? __('•••• stored — leave blank to keep') : __('Paste your client secret here') }}"
                   class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Stored encrypted at rest. Changing it clears the existing connection.') }}
            </p>
            @error('form.client_secret') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="submit"
                    class="rounded-lg bg-slate-900 dark:bg-slate-100 px-4 py-2 text-sm font-medium text-white dark:text-slate-900 hover:opacity-90 transition-opacity">
                {{ __('Save credentials') }}
            </button>

            @if($form['client_id'] && $form['has_secret'])
                <a href="{{ $connectUrl }}"
                   class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    {{ __('Connect to Google') }}
                </a>
            @endif

            @if($isConnected)
                <button type="button" wire:click="testConnection"
                        class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    {{ __('Test connection') }}
                </button>

                <button type="button" wire:click="disconnect"
                        wire:confirm="{{ __('Disconnect Google Sheets? You will need to run the OAuth flow again to reconnect.') }}"
                        class="text-sm text-rose-600 dark:text-rose-400 hover:underline">
                    {{ __('Disconnect') }}
                </button>
            @endif
        </div>
    </form>
</div>
