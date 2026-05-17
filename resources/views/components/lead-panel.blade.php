@props(['lead', 'statusOptions' => [], 'priorityOptions' => [], 'aiSummary' => null])

<div class="fixed inset-0 z-40 flex" x-data x-trap.noscroll="true" x-on:keydown.escape.window="$wire.closePanel()">
    <div class="flex-1 bg-slate-900/40 dark:bg-black/50" wire:click="closePanel"></div>

    <aside role="dialog" aria-modal="true" aria-labelledby="lead-panel-title"
           class="w-full max-w-[520px] bg-white dark:bg-slate-900 shadow-xl dark:shadow-black/40 flex flex-col border-l border-slate-200 dark:border-slate-700/50">
        <header class="border-b border-slate-200 dark:border-slate-700/50 px-5 py-4 flex items-start justify-between">
            <div>
                <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Lead #:id', ['id' => $lead->id]) }}</div>
                <h2 id="lead-panel-title" class="mt-0.5 text-lg font-semibold text-slate-900 dark:text-slate-50">{{ $lead->full_name ?? '—' }}</h2>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $lead->client_name ?? '—' }} · {{ $lead->campaign_name ?? '—' }}
                </div>
            </div>
            <button type="button" wire:click="closePanel" aria-label="{{ __('Close') }}"
                    class="text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">✕</button>
        </header>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">

            @if($lead->duplicate_flag)
                <div class="rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800/50 px-3 py-2 text-sm text-rose-800 dark:text-rose-300 flex items-center justify-between">
                    <span>
                        {{ __('Potential duplicate') }}
                        @if($lead->duplicateOf)
                            {{ __('of') }} <strong>#{{ $lead->duplicateOf->id }}</strong>
                            <span class="text-rose-700/80 dark:text-rose-400/80">— {{ $lead->duplicateOf->full_name ?? $lead->duplicateOf->email ?? $lead->duplicateOf->phone }}</span>
                        @endif
                    </span>
                    @if(auth()->user()?->isOperator())
                        <button type="button" wire:click="reconcileDuplicate({{ $lead->id }})" class="text-xs underline hover:no-underline">
                            {{ __('Re-check') }}
                        </button>
                    @endif
                </div>
            @endif

            {{-- contact --}}
            <section>
                <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Contact') }}</h3>
                <dl class="grid grid-cols-3 gap-y-2 text-sm">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Email') }}</dt>
                    <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ $lead->email ?? '—' }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Phone') }}</dt>
                    <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ $lead->phone ?? '—' }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Source') }}</dt>
                    <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ str_replace('_', ' ', $lead->source) }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Received') }}</dt>
                    <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ $lead->created_at?->format('Y-m-d H:i') }}</dd>
                </dl>
            </section>

            {{-- workflow --}}
            @if(auth()->user()?->isOperator())
                <section>
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Workflow') }}</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-500 dark:text-slate-400">{{ __('Status') }}</label>
                            <select wire:change="setStatus({{ $lead->id }}, $event.target.value)"
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach($statusOptions as $o)
                                    <option value="{{ $o['value'] }}" @selected($lead->status->value === $o['value'])>{{ $o['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500 dark:text-slate-400">{{ __('Priority') }}</label>
                            <select wire:change="setPriority({{ $lead->id }}, $event.target.value)"
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach($priorityOptions as $o)
                                    <option value="{{ $o['value'] }}" @selected($lead->priority->value === $o['value'])>{{ $o['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>
            @endif

            {{-- message --}}
            @if($lead->message)
                <section>
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Message') }}</h3>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/50 px-3 py-2 text-sm text-slate-800 dark:text-slate-200 whitespace-pre-wrap">{{ $lead->message }}</div>
                </section>
            @endif

            {{-- AI evaluation (operator only) --}}
            @if(config('lodgely.ai.enabled') && auth()->user()?->isOperator())
                <section>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('AI evaluation') }}</h3>
                        <button type="button" wire:click="evaluateLeadWithAi({{ $lead->id }})"
                                class="text-xs text-brand-600 dark:text-brand-400 hover:underline">
                            {{ __('Run AI evaluation') }}
                        </button>
                    </div>
                    @if($aiSummary)
                        <x-ai.summary-card :summary="$aiSummary" />
                    @else
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('No approved AI evaluation yet. Run one and review it in AI drafts.') }}</p>
                    @endif
                </section>
            @endif

            {{-- notes --}}
            <section>
                <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Notes') }}</h3>
                <div class="space-y-2">
                    @forelse($lead->notes as $note)
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 px-3 py-2 text-sm bg-white dark:bg-slate-800/40">
                            <div class="text-slate-800 dark:text-slate-200 whitespace-pre-wrap">{{ $note->body }}</div>
                            <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-500">
                                {{ $note->user?->name ?? 'system' }} · {{ $note->created_at?->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('No notes yet.') }}</p>
                    @endforelse
                </div>

                @if(auth()->user()?->isOperator())
                    <form wire:submit.prevent="addNote" class="mt-3">
                        <textarea wire:model="newNoteBody" rows="2" maxlength="5000" placeholder="{{ __('Add a short note…') }}"
                                  class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                        @error('newNoteBody') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        <div class="mt-2 flex justify-end">
                            <button type="submit"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                                class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">{{ __('Add note') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- audit trail --}}
            <section>
                <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Activity') }}</h3>
                <ul class="space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                    @foreach($lead->events->take(20) as $event)
                        <li class="flex items-center justify-between">
                            <span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ str_replace(['lead.', '_'], ['', ' '], $event->type) }}</span>
                                @if($event->user) · {{ $event->user->name }} @endif
                            </span>
                            <span class="text-slate-400 dark:text-slate-500">{{ $event->created_at?->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </aside>
</div>
