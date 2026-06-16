@php
    $inputClass = 'block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500';
    $labelClass = 'block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1';
@endphp

<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Email (SMTP)') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Configure the outgoing mail server used for reporting emails and password resets. Settings here are stored encrypted and override the .env mail config — no file editing needed.') }}
        </p>
    </div>

    @if($testResult)
        @php [$tone, $msg] = explode(':', $testResult, 2); @endphp
        <div class="rounded-lg px-3 py-2 text-sm
            {{ $tone === 'success'
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50'
                : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50' }}">
            {{ $msg }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-5 rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm">

        {{-- Master toggle --}}
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" wire:model.live="form.enabled"
                   class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span>
                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Use these mail settings') }}</span>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('When off, lodgely uses the MAIL_* values from .env (default: the log driver, which writes mail to the log instead of sending it — the usual reason reporting emails "don\'t arrive").') }}
                </p>
            </span>
        </label>

        {{-- Transport --}}
        <div>
            <label class="{{ $labelClass }}">{{ __('Transport') }}</label>
            <select wire:model.live="form.mailer" class="{{ $inputClass }}">
                <option value="smtp">{{ __('SMTP — send via a mail server') }}</option>
                <option value="log">{{ __('Log — write to the log, do not send (for testing)') }}</option>
            </select>
            @error('form.mailer') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        @if($form['mailer'] === 'smtp')
            <div class="grid sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="{{ $labelClass }}">{{ __('SMTP host') }}</label>
                    <input type="text" wire:model="form.host" autocomplete="off"
                           placeholder="smtp.example.com" class="{{ $inputClass }}">
                    @error('form.host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">{{ __('Port') }}</label>
                    <input type="number" wire:model="form.port" min="1" max="65535"
                           placeholder="{{ $form['encryption'] === 'ssl' ? '465' : '587' }}" class="{{ $inputClass }}">
                    @error('form.port') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="{{ $labelClass }}">{{ __('Encryption') }}</label>
                <select wire:model.live="form.encryption" class="{{ $inputClass }}">
                    <option value="tls">{{ __('STARTTLS (recommended, usually port 587)') }}</option>
                    <option value="ssl">{{ __('SSL / implicit TLS (usually port 465)') }}</option>
                    <option value="none">{{ __('None (unencrypted — not recommended)') }}</option>
                </select>
                @error('form.encryption') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelClass }}">{{ __('Username') }}</label>
                    <input type="text" wire:model="form.username" autocomplete="off"
                           placeholder="leads@example.com" class="{{ $inputClass }}">
                    @error('form.username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">{{ __('Password') }}</label>
                    <input type="password" wire:model="form.password" autocomplete="new-password"
                           placeholder="{{ $form['has_password'] ? __('•••• stored — leave blank to keep') : __('SMTP password / app password') }}"
                           class="{{ $inputClass }}">
                    <div class="mt-1 flex items-center justify-between">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Stored encrypted on disk. Never displayed again after saving.') }}</p>
                        @if($form['has_password'])
                            <button type="button" wire:click="clearPassword"
                                    class="text-xs text-rose-600 dark:text-rose-400 hover:underline">{{ __('Clear') }}</button>
                        @endif
                    </div>
                    @error('form.password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif

        {{-- From identity (applies to all transports) --}}
        <div class="grid sm:grid-cols-2 gap-4 border-t border-slate-100 dark:border-slate-800 pt-4">
            <div>
                <label class="{{ $labelClass }}">{{ __('From address') }}</label>
                <input type="email" wire:model="form.from_address" autocomplete="off"
                       placeholder="{{ config('mail.from.address') }}" class="{{ $inputClass }}">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Leave blank to keep the .env default.') }}</p>
                @error('form.from_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">{{ __('From name') }}</label>
                <input type="text" wire:model="form.from_name" autocomplete="off"
                       placeholder="{{ config('mail.from.name') }}" class="{{ $inputClass }}">
                @error('form.from_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end border-t border-slate-100 dark:border-slate-800 pt-4">
            <button type="submit"
                    class="rounded-lg bg-slate-900 dark:bg-slate-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
                {{ __('Save settings') }}
            </button>
        </div>
    </form>

    {{-- Send a test email --}}
    <div class="space-y-3 rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <div>
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Send a test email') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Save your settings first, then send a test. It goes out immediately, so any SMTP error (bad password, blocked port) is shown right here.') }}
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 sm:items-end">
            <div class="flex-1">
                <label class="{{ $labelClass }}">{{ __('Recipient') }}</label>
                <input type="email" wire:model="form.test_recipient" placeholder="you@example.com" class="{{ $inputClass }}">
                @error('form.test_recipient') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="sendTest" wire:loading.attr="disabled"
                    class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50">
                <span wire:loading.remove wire:target="sendTest">{{ __('Send test email') }}</span>
                <span wire:loading wire:target="sendTest">{{ __('Sending…') }}</span>
            </button>
        </div>
    </div>
</div>
