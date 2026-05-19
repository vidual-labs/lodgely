<x-layouts.app>
    <div class="max-w-2xl mx-auto space-y-6">
        <header>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
                {{ __('Google Sheets connected') }}
            </h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ __('Authorization succeeded. Copy the refresh token below into your .env file to persist the connection.') }}
            </p>
        </header>

        @if($refreshToken)
            <div class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ __('Refresh token') }}
                </div>
                <pre class="overflow-auto rounded bg-slate-50 dark:bg-slate-950 p-3 text-xs font-mono text-slate-800 dark:text-slate-200 break-all whitespace-pre-wrap">{{ $refreshToken }}</pre>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ __('Add to .env') }}
                </div>
<pre class="overflow-auto rounded bg-slate-50 dark:bg-slate-950 p-3 text-xs font-mono text-slate-800 dark:text-slate-200">LODGELY_GOOGLE_SHEETS_REFRESH_TOKEN={{ $refreshToken }}</pre>
                @if($scope !== '')
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Granted scopes:') }} <span class="font-mono">{{ $scope }}</span>
                    </p>
                @endif
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('After updating .env, restart the app (or clear the config cache) so the new value takes effect.') }}
                </p>
            </div>
        @else
            <div class="rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 p-4 text-sm text-amber-900 dark:text-amber-200">
                {{ __('Google did not return a refresh token. This usually means the OAuth client has already been authorized for this account. Revoke access at https://myaccount.google.com/permissions and try again.') }}
            </div>
        @endif

        <a href="{{ route('inbox') }}"
           class="inline-flex items-center text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100">
            &larr; {{ __('Back to inbox') }}
        </a>
    </div>
</x-layouts.app>
