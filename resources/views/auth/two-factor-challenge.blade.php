<x-layouts.guest>
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <a href="{{ url('/') }}" aria-label="{{ config('lodgely.brand.name') }}" style="display: inline-block;">
                <x-brand-logo height="2.5rem" />
            </a>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ __('Two-factor authentication') }}</p>
        </div>

        <form method="POST" action="{{ route('two-factor.verify') }}"
              class="rounded-2xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-6 space-y-4 shadow-xl shadow-slate-200/50 dark:shadow-black/40">
            @csrf
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Authentication code') }}</label>
                <input name="code" type="text" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus
                       class="mt-1 block w-full rounded-lg border-slate-300 py-3 px-4 text-sm tracking-widest focus:border-brand-500 focus:ring-brand-500">
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ __('Open your authenticator app and enter the 6-digit code.') }}</p>
                @error('code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <details class="text-xs text-slate-600 dark:text-slate-400">
                <summary class="cursor-pointer hover:text-slate-900 dark:hover:text-slate-100">{{ __('Lost your phone? Use a recovery code') }}</summary>
                <input name="recovery_code" type="text" autocomplete="off" spellcheck="false"
                       class="mt-2 block w-full rounded-lg border-slate-300 py-3 px-4 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
            </details>

            <button type="submit"
                    class="w-full rounded-lg bg-slate-900 dark:bg-brand-600 px-3 py-2.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-brand-500 transition-colors shadow-sm">
                {{ __('Verify') }}
            </button>

            <p class="text-center text-xs">
                <a href="{{ route('login') }}" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100">{{ __('Back to sign in') }}</a>
            </p>
        </form>
    </div>
</x-layouts.guest>
