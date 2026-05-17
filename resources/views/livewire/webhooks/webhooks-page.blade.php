<div class="space-y-6 max-w-4xl">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('Webhook endpoints') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Each endpoint has a unique URL. POST a JSON payload to it and lodgely ingests the lead immediately.') }}
            </p>
        </div>
        <button type="button" wire:click="openCreate"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors shadow-sm">
            + {{ __('New endpoint') }}
        </button>
    </div>

    {{-- example payload --}}
    <details class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 shadow-sm overflow-hidden">
        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 select-none transition-colors">
            {{ __('Example payload') }}
        </summary>
        <div class="border-t border-slate-100 dark:border-slate-800 px-4 py-3">
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">POST <code class="dark:text-slate-300">https://your-domain/api/webhooks/{token}</code> with <code class="dark:text-slate-300">Content-Type: application/json</code>.</p>
            <pre class="rounded-xl bg-slate-900 dark:bg-slate-950 text-emerald-300 text-xs p-4 overflow-x-auto leading-relaxed">{
  "full_name":     "Jane Doe",
  "email":         "jane@example.com",
  "phone":         "+44 7700 900123",
  "message":       "Interested in your services.",
  "client_name":   "Acme Wellness",
  "campaign_name": "Summer 2026"
}</pre>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                <strong>email</strong> or <strong>phone</strong> is required. All other fields are optional.
                <strong>client_name</strong> and <strong>campaign_name</strong> override the endpoint defaults when provided.
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Success: <code class="dark:text-slate-300">201</code> <code class="dark:text-slate-300">{"status":"accepted","lead_id":42,"duplicate":false}</code>.
                Rate limit: 60 requests per minute per endpoint.
            </p>
        </div>
    </details>

    {{-- endpoints table --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400">
                        <th class="px-3 py-2">{{ __('Label') }}</th>
                        <th class="px-3 py-2">{{ __('Default client') }}</th>
                        <th class="px-3 py-2">{{ __('Default campaign') }}</th>
                        <th class="px-3 py-2 w-[110px]">{{ __('Status') }}</th>
                        <th class="px-3 py-2 w-[120px]">{{ __('Last used') }}</th>
                        <th class="px-3 py-2 w-[180px] text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($endpoints as $ep)
                        <tr wire:key="ep-{{ $ep->id }}" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-3 py-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $ep->label }}</td>
                            <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ $ep->default_client_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ $ep->default_campaign_name ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @if($ep->is_active)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-400 ring-1 ring-inset ring-emerald-600/20 dark:ring-emerald-500/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-400 ring-1 ring-inset ring-slate-400/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> {{ __('Disabled') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                                {{ $ep->last_used_at?->diffForHumans() ?? __('Never') }}
                            </td>
                            <td class="px-3 py-2 text-right text-sm space-x-2">
                                <button wire:click="revealToken({{ $ep->id }})"
                                        class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors">
                                    {{ $revealedId === $ep->id ? __('Hide URL') : __('Show URL') }}
                                </button>
                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                <button wire:click="toggleActive({{ $ep->id }})"
                                        class="transition-colors {{ $ep->is_active ? 'text-slate-500 dark:text-slate-400 hover:text-rose-700 dark:hover:text-rose-400' : 'text-emerald-700 dark:text-emerald-500 hover:text-emerald-800 dark:hover:text-emerald-400' }}">
                                    {{ $ep->is_active ? __('Disable') : __('Enable') }}
                                </button>
                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                <button wire:click="delete({{ $ep->id }})"
                                        wire:confirm="{{ __('Delete this endpoint? Any integrations using it will stop working.') }}"
                                        class="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300 transition-colors">
                                    {{ __('Delete') }}
                                </button>
                            </td>
                        </tr>
                        @if($revealedId === $ep->id)
                            <tr wire:key="ep-token-{{ $ep->id }}" class="bg-slate-50 dark:bg-slate-800/40">
                                <td colspan="6" class="px-3 py-3">
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">{{ __('Webhook URL — keep this secret') }}</p>
                                    <div class="flex items-center gap-2">
                                        <code class="flex-1 rounded-xl bg-slate-900 dark:bg-slate-950 text-emerald-300 text-xs px-3 py-2 break-all select-all">
                                            {{ url('/api/webhooks/'.$ep->token) }}
                                        </code>
                                    </div>
                                    <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-500">
                                        {{ __('Anyone with this URL can submit leads. Rotate by deleting and recreating the endpoint.') }}
                                    </p>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('No webhook endpoints yet. Create one to start receiving leads from contact forms or integrations.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- create modal --}}
    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 dark:bg-black/60 px-4"
             x-data x-on:keydown.escape.window="$wire.close()"
             wire:click.self="close">
            <div role="dialog" aria-modal="true" aria-labelledby="webhooks-dialog-title"
                 class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-2xl dark:shadow-black/50 border border-slate-200 dark:border-slate-700/50">
                <header class="border-b border-slate-200 dark:border-slate-700/50 px-5 py-3 flex justify-between items-center">
                    <h2 id="webhooks-dialog-title" class="text-base font-semibold text-slate-900 dark:text-slate-50">{{ __('New webhook endpoint') }}</h2>
                    <button type="button" wire:click="close" aria-label="{{ __('Close') }}"
                            class="text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">✕</button>
                </header>

                <form wire:submit.prevent="save" class="px-5 py-4 space-y-3">
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Label') }}</label>
                        <input wire:model="form.label" type="text"
                               placeholder="{{ __('e.g. Acme contact form, Zapier trigger') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.label') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Default client name') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span></label>
                        <input wire:model="form.default_client_name" type="text"
                               placeholder="{{ __('Applied when payload omits client_name') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.default_client_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Default campaign') }} <span class="text-slate-400 dark:text-slate-500">{{ __('(optional)') }}</span></label>
                        <input wire:model="form.default_campaign_name" type="text"
                               class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.default_campaign_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('A unique secret URL will be generated. You can view it after saving.') }}
                    </p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="close"
                                class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                                class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">
                            {{ __('Create endpoint') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
