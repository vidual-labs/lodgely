<x-layouts.guest>
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <img src="{{ asset('img/logo.svg') }}"
                 alt="{{ config('lodgely.brand.name') }}"
                 class="mx-auto h-14 w-auto rounded-xl shadow-lg">
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ config('lodgely.brand.tagline') }}</p>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}"
              class="rounded-2xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-6 space-y-4 shadow-xl shadow-slate-200/50 dark:shadow-black/40">
            @csrf
            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Email') }}</label>
                <input name="email" type="email" required autofocus value="{{ old('email') }}"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Password') }}</label>
                <input name="password" type="password" required
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>

            <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-500 focus:ring-brand-500"> {{ __('Remember me') }}
            </label>

            <button type="submit"
                    class="w-full rounded-lg bg-slate-900 dark:bg-brand-600 px-3 py-2.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-brand-500 transition-colors shadow-sm">
                {{ __('Sign in') }}
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-500">
            {{ __('New deployment? Create the first operator via') }}
            <code class="text-slate-700 dark:text-slate-400">php artisan lodgely:user:create</code>.
        </p>
    </div>
</x-layouts.guest>
