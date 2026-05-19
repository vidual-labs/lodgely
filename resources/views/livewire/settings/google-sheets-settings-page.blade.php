<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Google Sheets') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Connect a Google account so lodgely can read data from Google Sheets. Set up an OAuth client in Google Cloud Console first, then paste the credentials here.') }}
        </p>
    </div>

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
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('From Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client IDs.') }}
            </p>
            @error('form.client_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Client secret') }}</label>
            <input type="password" wire:model="form.client_secret" autocomplete="new-password"
                   placeholder="{{ $form['has_secret'] ? __('•••• stored — leave blank to keep') : __('Paste your client secret here') }}"
                   class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Stored encrypted. Never displayed again after saving. Changing it clears the existing connection and requires a new authorize step.') }}
            </p>
            @error('form.client_secret') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/50 p-3 text-xs text-slate-600 dark:text-slate-400 space-y-1">
            <p class="font-medium text-slate-700 dark:text-slate-300">{{ __('Redirect URI to whitelist in Google Cloud Console') }}</p>
            <code class="font-mono select-all break-all">{{ route('settings.google-sheets.callback') }}</code>
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

    <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1">
        <p class="font-medium">{{ __('Setup steps') }}</p>
        <ol class="list-decimal list-inside space-y-0.5">
            <li>{{ __('Create an OAuth 2.0 Client (type "Web application") in Google Cloud Console.') }}</li>
            <li>{{ __('Add the redirect URI shown above to the authorized redirect URIs list.') }}</li>
            <li>{{ __('Paste the Client ID and Client secret above and click "Save credentials".') }}</li>
            <li>{{ __('Click "Connect to Google" and approve access on the consent screen.') }}</li>
        </ol>
    </div>
</div>
