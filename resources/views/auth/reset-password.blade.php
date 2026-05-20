<x-layouts.guest>
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <img src="{{ asset('img/logo.png') }}?v={{ filemtime(public_path('img/logo.png')) }}"
                 alt="{{ config('lodgely.brand.name') }}"
                 class="mx-auto h-24 w-auto">
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ __('Choose a new password') }}</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}"
              class="rounded-2xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-6 space-y-4 shadow-xl shadow-slate-200/50 dark:shadow-black/40">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Email') }}</label>
                <input name="email" type="email" required value="{{ old('email', $email) }}"
                       class="mt-1 block w-full rounded-lg border-slate-300 py-3 px-4 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('New password') }}</label>
                <input name="password" type="password" required autocomplete="new-password"
                       class="mt-1 block w-full rounded-lg border-slate-300 py-3 px-4 text-sm focus:border-brand-500 focus:ring-brand-500">
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-500">{{ __('Minimum 12 characters.') }}</p>
                @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Confirm new password') }}</label>
                <input name="password_confirmation" type="password" required autocomplete="new-password"
                       class="mt-1 block w-full rounded-lg border-slate-300 py-3 px-4 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-slate-900 dark:bg-brand-600 px-3 py-2.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-brand-500 transition-colors shadow-sm">
                {{ __('Update password') }}
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-500">
            <a href="{{ route('login') }}" class="hover:text-slate-700 dark:hover:text-slate-300">{{ __('Back to sign in') }}</a>
        </p>
    </div>
</x-layouts.guest>
