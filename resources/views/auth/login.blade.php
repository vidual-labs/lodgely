<x-layouts.guest>
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <div class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-slate-900 text-white text-sm font-bold">L</div>
            <h1 class="mt-3 text-lg font-semibold text-slate-900">{{ config('lodgely.brand.name') }}</h1>
            <p class="text-sm text-slate-500">{{ config('lodgely.brand.tagline') }}</p>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}" class="rounded-md border border-slate-200 bg-white p-5 space-y-4">
            @csrf
            <div>
                <label class="text-xs font-medium text-slate-600">Email</label>
                <input name="email" type="email" required autofocus value="{{ old('email') }}"
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600">Password</label>
                <input name="password" type="password" required
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </div>

            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"> Remember me
            </label>

            <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Sign in
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-500">
            New deployment? Create the first operator via
            <code class="text-slate-700">php artisan lodgely:user:create</code>.
        </p>
    </div>
</x-layouts.guest>
