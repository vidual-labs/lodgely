<x-layouts.app>
    <div class="max-w-2xl mx-auto space-y-6">
        <header>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
                {{ __('Google Sheets authorization failed') }}
            </h1>
        </header>

        <div class="rounded-lg border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 p-4 text-sm text-rose-900 dark:text-rose-200">
            {{ $message }}
        </div>

        <div class="text-sm text-slate-600 dark:text-slate-400 space-y-2">
            <p>{{ __('Common causes:') }}</p>
            <ul class="list-disc list-inside space-y-1">
                <li>{{ __('LODGELY_GOOGLE_SHEETS_CLIENT_ID or LODGELY_GOOGLE_SHEETS_CLIENT_SECRET is missing from .env.') }}</li>
                <li>{{ __('The redirect URI on the Google OAuth client does not match this app\'s callback URL.') }}</li>
                <li>{{ __('You did not grant the requested scopes on the consent screen.') }}</li>
            </ul>
        </div>

        <a href="{{ route('settings.google-sheets.connect') }}"
           class="inline-flex items-center rounded-md bg-slate-900 dark:bg-slate-100 px-4 py-2 text-sm font-medium text-white dark:text-slate-900 hover:opacity-90">
            {{ __('Try again') }}
        </a>
    </div>
</x-layouts.app>
