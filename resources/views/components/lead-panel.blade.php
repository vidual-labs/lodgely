@props(['lead', 'statusOptions' => [], 'priorityOptions' => []])

<div class="fixed inset-0 z-40 flex" x-data x-trap.noscroll="true">
    <div class="flex-1 bg-slate-900/30" wire:click="closePanel"></div>

    <aside role="dialog" aria-modal="true" aria-labelledby="lead-panel-title"
           class="w-full max-w-[520px] bg-white shadow-xl flex flex-col">
        <header class="border-b border-slate-200 px-5 py-4 flex items-start justify-between">
            <div>
                <div class="text-[11px] uppercase tracking-wider text-slate-500">Lead #{{ $lead->id }}</div>
                <h2 id="lead-panel-title" class="mt-0.5 text-lg font-semibold text-slate-900">{{ $lead->full_name ?? '—' }}</h2>
                <div class="text-xs text-slate-500">
                    {{ $lead->client_name ?? '—' }} · {{ $lead->campaign_name ?? '—' }}
                </div>
            </div>
            <button type="button" wire:click="closePanel" aria-label="Close"
                    class="text-slate-400 hover:text-slate-700">✕</button>
        </header>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">

            @if($lead->duplicate_flag)
                <div class="rounded-md bg-rose-50 border border-rose-200 px-3 py-2 text-sm text-rose-800 flex items-center justify-between">
                    <span>
                        Potential duplicate
                        @if($lead->duplicateOf)
                            of <strong>#{{ $lead->duplicateOf->id }}</strong>
                            <span class="text-rose-700/80">— {{ $lead->duplicateOf->full_name ?? $lead->duplicateOf->email ?? $lead->duplicateOf->phone }}</span>
                        @endif
                    </span>
                    <button type="button" wire:click="reconcileDuplicate({{ $lead->id }})" class="text-xs underline">
                        Re-check
                    </button>
                </div>
            @endif

            {{-- contact --}}
            <section>
                <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-2">Contact</h3>
                <dl class="grid grid-cols-3 gap-y-2 text-sm">
                    <dt class="text-slate-500">Email</dt>
                    <dd class="col-span-2 text-slate-800">{{ $lead->email ?? '—' }}</dd>
                    <dt class="text-slate-500">Phone</dt>
                    <dd class="col-span-2 text-slate-800">{{ $lead->phone ?? '—' }}</dd>
                    <dt class="text-slate-500">Source</dt>
                    <dd class="col-span-2 text-slate-800">{{ str_replace('_', ' ', $lead->source) }}</dd>
                    <dt class="text-slate-500">Received</dt>
                    <dd class="col-span-2 text-slate-800">{{ $lead->created_at?->format('Y-m-d H:i') }}</dd>
                </dl>
            </section>

            {{-- workflow --}}
            <section>
                <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-2">Workflow</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-slate-500">Status</label>
                        <select wire:change="setStatus({{ $lead->id }}, $event.target.value)"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach($statusOptions as $o)
                                <option value="{{ $o['value'] }}" @selected($lead->status->value === $o['value'])>{{ $o['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Priority</label>
                        <select wire:change="setPriority({{ $lead->id }}, $event.target.value)"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach($priorityOptions as $o)
                                <option value="{{ $o['value'] }}" @selected($lead->priority->value === $o['value'])>{{ $o['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            {{-- message --}}
            @if($lead->message)
                <section>
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-2">Message</h3>
                    <div class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-800 whitespace-pre-wrap">{{ $lead->message }}</div>
                </section>
            @endif

            {{-- notes --}}
            <section>
                <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-2">Notes</h3>
                <div class="space-y-2">
                    @forelse($lead->notes as $note)
                        <div class="rounded-md border border-slate-200 px-3 py-2 text-sm">
                            <div class="text-slate-800 whitespace-pre-wrap">{{ $note->body }}</div>
                            <div class="mt-1 text-[11px] text-slate-500">
                                {{ $note->user?->name ?? 'system' }} · {{ $note->created_at?->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">No notes yet.</p>
                    @endforelse
                </div>

                <form wire:submit.prevent="addNote" class="mt-3">
                    <textarea wire:model="newNoteBody" rows="2" maxlength="5000" placeholder="Add a short note…"
                              class="block w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                    @error('newNoteBody') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="mt-2 flex justify-end">
                        <button type="submit"
                            wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                            class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800">Add note</button>
                    </div>
                </form>
            </section>

            {{-- audit trail --}}
            <section>
                <h3 class="text-xs uppercase tracking-wider text-slate-500 mb-2">Activity</h3>
                <ul class="space-y-1.5 text-xs text-slate-600">
                    @foreach($lead->events->take(20) as $event)
                        <li class="flex items-center justify-between">
                            <span>
                                <span class="font-medium text-slate-800">{{ str_replace(['lead.', '_'], ['', ' '], $event->type) }}</span>
                                @if($event->user) · {{ $event->user->name }} @endif
                            </span>
                            <span class="text-slate-400">{{ $event->created_at?->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </aside>
</div>
