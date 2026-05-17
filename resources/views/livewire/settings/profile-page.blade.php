<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Profile') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Update your name, email, password and display preferences.') }}
            @if($user->isClient())
                <span class="ml-1 inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:text-slate-400">
                    {{ __('Client') }}
                </span>
            @else
                <span class="ml-1 inline-flex items-center rounded-md bg-indigo-50 dark:bg-indigo-950/60 px-1.5 py-0.5 text-[11px] font-medium text-indigo-700 dark:text-indigo-400">
                    {{ __('Operator') }}
                </span>
            @endif
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Profile details --}}
        <form wire:submit.prevent="saveProfile"
              class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 space-y-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Account details') }}</h2>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Name') }}</label>
                <input wire:model="profile.name" type="text"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('profile.name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Email') }}</label>
                <input wire:model="profile.email" type="email"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('profile.email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Language') }}</label>
                    <select wire:model="profile.locale"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="en">{{ __('English') }}</option>
                        <option value="de">{{ __('German') }}</option>
                    </select>
                    @error('profile.locale') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Theme') }}</label>
                    <select wire:model="profile.theme"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="light">{{ __('Light') }}</option>
                        <option value="dark">{{ __('Dark') }}</option>
                    </select>
                    @error('profile.theme') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                        class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">
                    {{ __('Save profile') }}
                </button>
            </div>
        </form>

        {{-- Password change --}}
        <form wire:submit.prevent="changePassword"
              class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 space-y-4 shadow-sm">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Change password') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Use at least 12 characters. If you have forgotten the current one, sign out and use the') }}
                    <a href="{{ route('password.request') }}" class="text-slate-700 dark:text-slate-300 underline hover:no-underline">{{ __('reset link') }}</a>.
                </p>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Current password') }}</label>
                <input wire:model="password.current" type="password" autocomplete="current-password"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('password.current') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('New password') }}</label>
                <input wire:model="password.new" type="password" autocomplete="new-password"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('password.new') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Confirm new password') }}</label>
                <input wire:model="password.confirmation" type="password" autocomplete="new-password"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('password.confirmation') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                        class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">
                    {{ __('Update password') }}
                </button>
            </div>
        </form>
    </div>
</div>
