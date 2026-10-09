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

            <div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Current password') }}</label>
                <input wire:model="profile.current_password" type="password" autocomplete="current-password"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ __('Only needed when you change your email.') }}</p>
                @error('profile.current_password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
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

    {{-- AI ranking profile (client users). The operator-wide profile lives on
         /settings/ai; this is the client's own "ideal customer" text, one per
         client name they are scoped to. --}}
    @if($rankingEnabled)
        <form wire:submit.prevent="saveRankingProfiles"
              class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 space-y-4 shadow-sm">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('AI ranking profile') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Describe your ideal customer — the AI reads this when it ranks your new leads. Example: "We cater dinners for 20–30 guests with at least 40 EUR per person, in Leipzig or Halle."') }}
                </p>
            </div>

            @foreach($rankingProfiles as $i => $row)
                <div wire:key="ranking-profile-{{ $i }}">
                    @if(count($rankingProfiles) > 1)
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ $row['client_name'] }}</label>
                    @endif
                    <textarea wire:model="rankingProfiles.{{ $i }}.profile" rows="3" maxlength="{{ \App\Models\ClientAiProfile::MAX_LENGTH }}"
                              class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                    @error('rankingProfiles.'.$i.'.profile') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="flex justify-end pt-2">
                <button type="submit"
                        wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                        class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">
                    {{ __('Save ranking profile') }}
                </button>
            </div>
        </form>
    @endif

    {{-- Two-factor authentication (operators; optional). Plain POST forms to
         TwoFactorController — not Livewire actions, see CLAUDE.md gotchas. --}}
    @if($user->canEnableTwoFactor() || $user->hasTwoFactorEnabled())
        @php
            $tfErrors = session('errors')?->getBag('twoFactor') ?? new \Illuminate\Support\MessageBag();
            $tfStatus = session('two-factor.status');
            $tfCodes  = session('two-factor.recovery-codes');
            $tfInput  = 'mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500';
            $tfButton = 'rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors';
        @endphp
        <section id="two-factor"
                 class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 space-y-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Two-factor authentication') }}</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Optional. After your password, sign-in also asks for a 6-digit code from an authenticator app (e.g. 1Password, Google Authenticator, Authy).') }}
                    </p>
                </div>
                @if($user->hasTwoFactorEnabled())
                    <span class="shrink-0 inline-flex items-center rounded-md bg-emerald-50 dark:bg-emerald-950/50 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">{{ __('On') }}</span>
                @else
                    <span class="shrink-0 inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:text-slate-400">{{ __('Off') }}</span>
                @endif
            </div>

            @if($tfStatus)
                <div class="rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-3 py-2 text-xs text-emerald-800 dark:text-emerald-300">
                    {{ $tfStatus }}
                </div>
            @endif

            @if(is_array($tfCodes) && $tfCodes !== [])
                <div class="rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-3 py-3 space-y-2">
                    <p class="text-xs text-amber-800 dark:text-amber-300">
                        {{ __('Recovery codes: each works once if you lose your phone. They are shown only now, so save them in your password manager.') }}
                    </p>
                    <ul class="grid grid-cols-2 gap-1 font-mono text-sm text-slate-900 dark:text-slate-100">
                        @foreach($tfCodes as $code)
                            <li class="px-1.5 py-0.5">{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($user->hasTwoFactorEnabled())
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ trans_choice(':count recovery code left.|:count recovery codes left.', count($user->two_factor_recovery_codes ?? []), ['count' => count($user->two_factor_recovery_codes ?? [])]) }}
                </p>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <form method="POST" action="{{ route('two-factor.recovery-codes') }}" class="space-y-3">
                        @csrf
                        <h3 class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('New recovery codes') }}</h3>
                        <div>
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Current password') }}</label>
                            <input name="password" type="password" autocomplete="current-password" required class="{{ $tfInput }}">
                        </div>
                        <button type="submit" class="{{ $tfButton }}">{{ __('Generate new recovery codes') }}</button>
                    </form>

                    <form method="POST" action="{{ route('two-factor.disable') }}" class="space-y-3">
                        @csrf
                        <h3 class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Turn off two-factor authentication') }}</h3>
                        <div>
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Current password') }}</label>
                            <input name="password" type="password" autocomplete="current-password" required class="{{ $tfInput }}">
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Authentication code or recovery code') }}</label>
                            <input name="code" type="text" inputmode="text" autocomplete="one-time-code" required class="{{ $tfInput }}">
                        </div>
                        <button type="submit" class="rounded-lg border border-rose-300 dark:border-rose-800 px-3 py-1.5 text-sm font-medium text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                            {{ __('Turn off') }}
                        </button>
                    </form>
                </div>
            @elseif($user->hasPendingTwoFactorSetup())
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                    <div class="space-y-2">
                        <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('1. Scan this QR code with your authenticator app.') }}</p>
                        <div class="inline-block rounded-lg bg-white p-2 border border-slate-200">{!! $twoFactorQr !!}</div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Can\'t scan it? Enter this key manually:') }}</p>
                        <code class="block break-all rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-1 font-mono text-xs text-slate-800 dark:text-slate-200">{{ trim(chunk_split($twoFactorKey, 4, ' ')) }}</code>
                    </div>

                    <div class="space-y-3">
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('2. Enter the 6-digit code it shows') }}</label>
                                <input name="code" type="text" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required autofocus class="{{ $tfInput }}">
                            </div>
                            <button type="submit" class="{{ $tfButton }}">{{ __('Confirm and turn on') }}</button>
                        </form>
                        <form method="POST" action="{{ route('two-factor.cancel') }}">
                            @csrf
                            <button type="submit" class="text-xs text-slate-500 dark:text-slate-400 underline hover:no-underline">{{ __('Cancel setup') }}</button>
                        </form>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('two-factor.enable') }}" class="max-w-sm space-y-3">
                    @csrf
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Current password') }}</label>
                        <input name="password" type="password" autocomplete="current-password" required class="{{ $tfInput }}">
                    </div>
                    <button type="submit" class="{{ $tfButton }}">{{ __('Set up two-factor authentication') }}</button>
                </form>
            @endif

            @foreach(['password', 'code'] as $field)
                @if($tfErrors->has($field))
                    <p class="text-xs text-rose-600">{{ $tfErrors->first($field) }}</p>
                @endif
            @endforeach
        </section>
    @endif
</div>
