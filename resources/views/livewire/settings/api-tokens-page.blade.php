@php
    $inputClass = 'block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500';
    $labelClass = 'block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1';
@endphp
<div class="space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('API tokens') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Read-only tokens for other tools. vigilo uses one to read lead metadata (status, flags and ad ids) so it can tell when leads stop arriving. The feed never contains names, email addresses, phone numbers, messages, answers or click ids.') }}
        </p>
    </div>

    @if($createdToken)
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-300 space-y-2"
             x-data="{ copied: false }">
            <p class="font-medium">{{ __('Token created. Copy it now — it will not be shown again.') }}</p>
            <div class="flex items-center gap-2 flex-wrap">
                <code x-ref="token" class="rounded-lg bg-white dark:bg-slate-900 border border-emerald-200 dark:border-emerald-800/50 px-3 py-1.5 font-mono text-xs break-all select-all">{{ $createdToken }}</code>
                <button type="button"
                        x-on:click="navigator.clipboard.writeText($refs.token.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        class="rounded-lg border border-emerald-300 dark:border-emerald-700 px-2.5 py-1 text-xs font-medium hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors">
                    <span x-show="!copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </button>
            </div>
            <p class="text-xs">{{ __('In vigilo, set it as VIGILO_LODGELY_API_TOKEN and this server URL as VIGILO_LODGELY_URL.') }}</p>
        </div>
    @endif

    @if($notice)
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-300">
            {{ $notice }}
        </div>
    @endif

    {{-- Create: a native form post, not a Livewire action (see ApiTokenController). --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Create a token') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Only a hash is stored. If a token is lost, revoke it and create a new one.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('settings.api-tokens.store') }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            @csrf
            <div>
                <label for="api-token-name" class="{{ $labelClass }}">{{ __('Name') }}</label>
                <input id="api-token-name" name="name" type="text" required maxlength="120"
                       value="{{ old('name') }}" placeholder="{{ __('e.g. vigilo') }}" class="{{ $inputClass }}">
                @error('name') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="api-token-scope" class="{{ $labelClass }}">{{ __('Access') }}</label>
                <select id="api-token-scope" name="scope" class="{{ $inputClass }}">
                    @foreach($scopes as $scope)
                        <option value="{{ $scope->value }}">{{ $scope->label() }}</option>
                    @endforeach
                </select>
                @error('scope') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
            <button type="submit"
                    class="rounded-lg bg-slate-900 dark:bg-slate-100 px-4 py-2 text-sm font-medium text-white dark:text-slate-900 hover:opacity-90 transition-opacity">
                {{ __('Create token') }}
            </button>
        </form>
    </div>

    {{-- List --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Tokens') }}</h2>

        <div class="rounded-xl border border-slate-100 dark:border-slate-800 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Name') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Token') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Created') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Last used') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($tokens as $token)
                        <tr @class(['opacity-60' => $token->isRevoked()])>
                            <td class="px-3 py-2 text-slate-800 dark:text-slate-200">
                                <div class="font-medium">{{ $token->name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ collect($token->grantedScopes())->map->label()->implode(', ') }}
                                </div>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-600 dark:text-slate-400">{{ $token->token_prefix }}…</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">
                                {{ $token->created_at?->format('Y-m-d H:i') }}
                                @if($token->user)
                                    <div class="text-xs">{{ $token->user->name }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">
                                {{ $token->last_used_at?->format('Y-m-d H:i') ?? __('Never') }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($token->isRevoked())
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Revoked :date', ['date' => $token->revoked_at->format('Y-m-d')]) }}</span>
                                @else
                                    <form method="POST" action="{{ route('settings.api-tokens.revoke', $token->id) }}"
                                          x-on:submit="if (! confirm(@js(__('Revoke :name? Tools using this token stop working immediately.', ['name' => $token->name])))) $event.preventDefault()">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-lg border border-rose-300 dark:border-rose-700 px-2.5 py-1 text-xs font-medium text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                                            {{ __('Revoke') }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('No API tokens yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
