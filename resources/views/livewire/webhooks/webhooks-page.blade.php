<div class="space-y-6 max-w-4xl">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Webhook endpoints</h1>
            <p class="text-sm text-slate-500">
                Each endpoint has a unique URL. POST a JSON payload to it and lodgely ingests the lead immediately.
            </p>
        </div>
        <button type="button" wire:click="openCreate"
                class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
            + New endpoint
        </button>
    </div>

    {{-- example payload --}}
    <details class="rounded-md border border-slate-200 bg-white">
        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 select-none">
            Example payload
        </summary>
        <div class="border-t border-slate-100 px-4 py-3">
            <p class="text-xs text-slate-500 mb-2">POST <code>https://your-domain/api/webhooks/{token}</code> with <code>Content-Type: application/json</code>.</p>
            <pre class="rounded-md bg-slate-900 text-emerald-300 text-xs p-4 overflow-x-auto leading-relaxed">{
  "full_name":     "Jane Doe",
  "email":         "jane@example.com",
  "phone":         "+44 7700 900123",
  "message":       "Interested in your services.",
  "client_name":   "Acme Wellness",
  "campaign_name": "Summer 2026"
}</pre>
            <p class="mt-2 text-xs text-slate-500">
                <strong>email</strong> or <strong>phone</strong> is required. All other fields are optional.
                <strong>client_name</strong> and <strong>campaign_name</strong> override the endpoint defaults when provided.
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Success: <code>201</code> <code>{"status":"accepted","lead_id":42,"duplicate":false}</code>.
                Rate limit: 60 requests per minute per endpoint.
            </p>
        </div>
    </details>

    {{-- endpoints table --}}
    <div class="rounded-md border border-slate-200 bg-white overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold text-slate-500">
                        <th class="px-3 py-2">Label</th>
                        <th class="px-3 py-2">Default client</th>
                        <th class="px-3 py-2">Default campaign</th>
                        <th class="px-3 py-2 w-[110px]">Status</th>
                        <th class="px-3 py-2 w-[120px]">Last used</th>
                        <th class="px-3 py-2 w-[180px] text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($endpoints as $ep)
                        <tr wire:key="ep-{{ $ep->id }}" class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-sm font-medium text-slate-900">{{ $ep->label }}</td>
                            <td class="px-3 py-2 text-sm text-slate-600">{{ $ep->default_client_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-sm text-slate-600">{{ $ep->default_campaign_name ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @if($ep->is_active)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-600/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-400/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Disabled
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500">
                                {{ $ep->last_used_at?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-3 py-2 text-right text-sm space-x-2">
                                <button wire:click="revealToken({{ $ep->id }})"
                                        class="text-slate-500 hover:text-slate-900">
                                    {{ $revealedId === $ep->id ? 'Hide URL' : 'Show URL' }}
                                </button>
                                <span class="text-slate-300">·</span>
                                <button wire:click="toggleActive({{ $ep->id }})"
                                        class="{{ $ep->is_active ? 'text-slate-500 hover:text-rose-700' : 'text-emerald-700 hover:text-emerald-800' }}">
                                    {{ $ep->is_active ? 'Disable' : 'Enable' }}
                                </button>
                                <span class="text-slate-300">·</span>
                                <button wire:click="delete({{ $ep->id }})"
                                        wire:confirm="Delete this endpoint? Any integrations using it will stop working."
                                        class="text-rose-600 hover:text-rose-800">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        @if($revealedId === $ep->id)
                            <tr wire:key="ep-token-{{ $ep->id }}" class="bg-slate-50">
                                <td colspan="6" class="px-3 py-3">
                                    <p class="text-xs text-slate-500 mb-1 font-medium">Webhook URL — keep this secret</p>
                                    <div class="flex items-center gap-2">
                                        <code class="flex-1 rounded-md bg-slate-900 text-emerald-300 text-xs px-3 py-2 break-all select-all">
                                            {{ url('/api/webhooks/'.$ep->token) }}
                                        </code>
                                    </div>
                                    <p class="mt-1.5 text-xs text-slate-400">
                                        Anyone with this URL can submit leads. Rotate by deleting and recreating the endpoint.
                                    </p>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-10 text-center text-sm text-slate-500">
                                No webhook endpoints yet. Create one to start receiving leads from contact forms or integrations.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- create modal --}}
    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div role="dialog" aria-modal="true" aria-labelledby="webhooks-dialog-title"
                 class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <header class="border-b border-slate-200 px-5 py-3 flex justify-between items-center">
                    <h2 id="webhooks-dialog-title" class="text-base font-semibold text-slate-900">New webhook endpoint</h2>
                    <button type="button" wire:click="close" aria-label="Close"
                            class="text-slate-400 hover:text-slate-700">✕</button>
                </header>

                <form wire:submit.prevent="save" class="px-5 py-4 space-y-3">
                    <div>
                        <label class="text-xs font-medium text-slate-600">Label</label>
                        <input wire:model="form.label" type="text"
                               placeholder="e.g. Acme contact form, Zapier trigger"
                               class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('form.label') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-600">Default client name <span class="text-slate-400">(optional)</span></label>
                        <input wire:model="form.default_client_name" type="text"
                               placeholder="Applied when payload omits client_name"
                               class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('form.default_client_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-600">Default campaign <span class="text-slate-400">(optional)</span></label>
                        <input wire:model="form.default_campaign_name" type="text"
                               class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('form.default_campaign_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-xs text-slate-500">
                        A unique secret URL will be generated. You can view it after saving.
                    </p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="close"
                                class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                                class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                            Create endpoint
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
