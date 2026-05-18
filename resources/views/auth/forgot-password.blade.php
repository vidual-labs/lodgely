<x-layouts.guest>
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <img src="{{ asset('img/logo.png') }}"
                 alt="{{ config('lodgely.brand.name') }}"
                 class="mx-auto h-14 w-auto rounded-xl shadow-lg">
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ __('Reset your password') }}</p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-3 py-2 text-xs text-emerald-800 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}"
              class="rounded-2xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-6 space-y-4 shadow-xl shadow-slate-200/50 dark:shadow-black/40">
            @csrf

            <p class="text-xs text-slate-600 dark:text-slate-400">
                {{ __('Enter the email tied to your account. If it matches an active user, we will email you a link to set a new password.') }}
            </p>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Email') }}</label>
                <input name="email" type="email" required autofocus value="{{ old('email') }}"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-slate-900 dark:bg-brand-600 px-3 py-2.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-brand-500 transition-colors shadow-sm">
                {{ __('Email reset link') }}
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-500">
            <a href="{{ route('login') }}" class="hover:text-slate-700 dark:hover:text-slate-300">{{ __('Back to sign in') }}</a>
        </p>
    </div>
</x-layouts.guest>
