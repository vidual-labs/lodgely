@props([
    'lead',
    'statusOptions' => [],
    'statusGroups' => [],
    'priorityOptions' => [],
    'noteSnippets' => [],
    'aiSummary' => null,
])

{{-- `statusNudge` holds the status value a just-inserted note snippet implies;
     the matching status pill pulses while it is set. Same nudge idea as
     calledNudge/mailedNudge above it — suggest, never write. --}}
<div class="fixed inset-0 z-40 flex"
     x-data="{ calledNudge: false, mailedNudge: false, statusNudge: null }"
     x-trap.noscroll="true" x-on:keydown.escape.window="$wire.closePanel()">
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
                    <dd class="col-span-2 text-slate-800 dark:text-slate-200">
                        @if($lead->email)
                            <a href="mailto:{{ $lead->email }}"
                               x-on:click="mailedNudge = true; setTimeout(() => mailedNudge = false, 15000)"
                               class="inline-flex items-center gap-1.5 text-brand-700 dark:text-brand-400 hover:underline">
                                <span aria-hidden="true">✉️</span>{{ $lead->email }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Phone') }}</dt>
                    <dd class="col-span-2 text-slate-800 dark:text-slate-200">
                        @if($lead->phone)
                            <a href="tel:{{ $lead->phone }}"
                               x-on:click="calledNudge = true; setTimeout(() => calledNudge = false, 15000)"
                               class="inline-flex items-center gap-1.5 text-brand-700 dark:text-brand-400 hover:underline">
                                <span aria-hidden="true">📞</span>{{ $lead->phone }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Source') }}</dt>
                    <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ str_replace('_', ' ', $lead->source) }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Received') }}</dt>
                    <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ $lead->created_at?->format('Y-m-d H:i') }}</dd>
                </dl>
            </section>

            {{-- outreach (client-driven) — qualified / called / mailed toggles.
                 Kept directly under Contact, alongside Workflow below, so the
                 two editable sections are the first thing anyone — client or
                 operator — sees on opening a lead, rather than buried under
                 the read-only attribution/message sections. --}}
            @php
                $outreach = [
                    ['field' => 'qualified_at', 'label' => __('Qualified'), 'value' => $lead->qualified_at,
                     'on' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-500/30',
                     'off' => 'bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 ring-slate-300/40 dark:ring-slate-600/40'],
                    ['field' => 'called_at',    'label' => __('Called'),    'value' => $lead->called_at,
                     'on' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-800 dark:text-sky-300 ring-sky-600/20 dark:ring-sky-500/30',
                     'off' => 'bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 ring-slate-300/40 dark:ring-slate-600/40'],
                    ['field' => 'mailed_at',    'label' => __('Mailed'),    'value' => $lead->mailed_at,
                     'on' => 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-800 dark:text-indigo-300 ring-indigo-600/20 dark:ring-indigo-500/30',
                     'off' => 'bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 ring-slate-300/40 dark:ring-slate-600/40'],
                ];
            @endphp
            <section class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-slate-50/60 dark:bg-slate-800/30 p-3">
                <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Outreach') }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($outreach as $o)
                        @php $nudgeVar = $o['field'] === 'called_at' ? 'calledNudge' : ($o['field'] === 'mailed_at' ? 'mailedNudge' : null); @endphp
                        <button type="button"
                                wire:click="toggleOutreach({{ $lead->id }}, '{{ $o['field'] }}')"
                                @if($nudgeVar) x-bind:class="{ 'ring-2 ring-offset-1 dark:ring-offset-slate-900 animate-pulse': {{ $nudgeVar }} }" @endif
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-colors {{ $o['value'] ? $o['on'] : $o['off'] }}"
                                title="{{ $o['value'] ? __(':label · :when', ['label' => $o['label'], 'when' => $o['value']->format('Y-m-d H:i')]) : __('Mark as :label', ['label' => mb_strtolower($o['label'])]) }}">
                            <span aria-hidden="true">{{ $o['value'] ? '✓' : '○' }}</span>
                            <span>{{ $o['label'] }}</span>
                            @if($o['value'])
                                <span class="text-[10px] opacity-70">· {{ $o['value']->diffForHumans() }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500" x-show="calledNudge || mailedNudge" x-cloak>
                    {{ __('Reached them? Confirm by tapping the highlighted pill — a dial or compose window opening doesn\'t mean the call connected or the email was actually sent.') }}
                </p>
                <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500" x-show="!(calledNudge || mailedNudge)">
                    {{ __('Click a pill to toggle.') }}
                </p>
            </section>

            {{-- workflow (status / priority) — both operators and clients may
                 change these on a lead they can see; {@see setStatus()} /
                 {@see setPriority()} on InboxPage do the actual scoping.

                 Status is a pill row rather than a dropdown: clients use the
                 Outreach pills above constantly and left the old status
                 <select> almost untouched, so the two sections now read and
                 behave the same way. Grouped intake / outcome so nine states
                 stay scannable. --}}
            @php
                // Events are eager-loaded latest-first, so the first status
                // event is the most recent one — "how long has this offer been
                // sitting?" without another query or a new column.
                $lastStatusChange = $lead->events->firstWhere('type', 'lead.status_changed');
            @endphp
            <section class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-slate-50/60 dark:bg-slate-800/30 p-3">
                <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Workflow') }}</h3>

                <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('Status') }}</div>
                <div class="mt-1 space-y-1">
                    @foreach($statusGroups as $group)
                        <div class="flex flex-wrap items-center gap-1">
                            <span class="px-1 py-0.5 text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500 w-16 shrink-0">{{ $group['label'] }}</span>
                            @foreach($group['options'] as $o)
                                @php $isCurrent = $lead->status->value === $o['value']; @endphp
                                <button type="button"
                                        wire:click="setStatus({{ $lead->id }}, '{{ $o['value'] }}')"
                                        aria-pressed="{{ $isCurrent ? 'true' : 'false' }}"
                                        x-bind:class="{ 'ring-2 ring-offset-1 dark:ring-offset-slate-900 animate-pulse': statusNudge === @js($o['value']) }"
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs ring-1 ring-inset transition-colors {{ $isCurrent ? $o['badge'].' font-semibold' : 'bg-white dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 ring-slate-300/60 dark:ring-slate-600/40 hover:text-slate-800 dark:hover:text-slate-200' }}"
                                        title="{{ $isCurrent ? $o['label'] : __('Set status to :label', ['label' => $o['label']]) }}">
                                    @if($isCurrent)<span aria-hidden="true">✓</span>@endif
                                    <span>{{ $o['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <p class="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500">
                    @if($lastStatusChange)
                        {{ __('Status set :when', ['when' => $lastStatusChange->created_at?->diffForHumans()]) }} ·
                    @endif
                    {{ __('Opening a lead marks it Reviewed; the first outreach pill marks it Pending. Set the rest yourself.') }}
                </p>

                <div class="mt-3 max-w-[220px]">
                    <label class="text-xs text-slate-500 dark:text-slate-400">{{ __('Priority') }}</label>
                    <select wire:change="setPriority({{ $lead->id }}, $event.target.value)"
                            class="mt-1 block w-full rounded-lg border-slate-300 py-3 px-4 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach($priorityOptions as $o)
                            <option value="{{ $o['value'] }}" @selected($lead->priority->value === $o['value'])>{{ $o['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            {{-- ad source (Meta Lead Ads attribution) --}}
            @if($lead->source === 'meta_ads' || $lead->meta_lead_id || $lead->ad_id || $lead->form_id)
                <section>
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Ad source') }}</h3>
                    <dl class="grid grid-cols-3 gap-y-2 text-sm">
                        @if($lead->platform)
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Platform') }}</dt>
                            <dd class="col-span-2 text-slate-800 dark:text-slate-200 capitalize">
                                {{ $lead->platform }}
                                @if($lead->is_organic !== null)
                                    <span class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset {{ $lead->is_organic ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20' : 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 ring-blue-600/20' }}">
                                        {{ $lead->is_organic ? __('Organic') : __('Paid') }}
                                    </span>
                                @endif
                            </dd>
                        @endif
                        @if($lead->campaign_name)
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Campaign') }}</dt>
                            <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ $lead->campaign_name }}</dd>
                        @endif
                        @if($lead->adset_name)
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Adset') }}</dt>
                            <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ $lead->adset_name }}</dd>
                        @endif
                        @if($lead->ad_name)
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Ad') }}</dt>
                            <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ $lead->ad_name }}</dd>
                        @endif
                        @if($lead->form_name)
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Form') }}</dt>
                            <dd class="col-span-2 text-slate-800 dark:text-slate-200">{{ $lead->form_name }}</dd>
                        @endif
                        @if(auth()->user()?->isOperator() && $lead->meta_lead_id)
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Meta lead ID') }}</dt>
                            <dd class="col-span-2 text-slate-500 dark:text-slate-400 font-mono text-xs">{{ $lead->meta_lead_id }}</dd>
                        @endif
                    </dl>
                </section>
            @endif

            {{-- custom questions (form Q&A from Meta or other lead forms) --}}
            @if(is_array($lead->custom_answers) && count($lead->custom_answers) > 0)
                <section>
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Custom questions') }}</h3>
                    <dl class="space-y-2 text-sm">
                        @foreach($lead->custom_answers as $qa)
                            <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/50 px-3 py-2">
                                <dt class="text-xs text-slate-500 dark:text-slate-400">{{ $qa['question'] ?? __('Question') }}</dt>
                                <dd class="mt-0.5 text-slate-800 dark:text-slate-200">{{ $qa['answer'] ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
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

                {{-- Both operators and clients may add notes — addNote() on InboxPage
                     scopes the target lead the same way every other action here does. --}}
                @auth
                    <form wire:submit.prevent="addNote" class="mt-3">
                        {{-- Quick phrases. Alpine writes the textarea directly and
                             pushes the value to Livewire deferred ($wire.set(…, false)) —
                             a wire:click round-trip here is exactly the morph-layer
                             drop documented in CLAUDE.md, and a deferred set also
                             avoids clobbering whatever the user is mid-way through
                             typing. Snippets that imply an outcome light up the
                             matching status pill instead of writing it. --}}
                        <div class="mb-2 flex flex-wrap gap-1" role="group" aria-label="{{ __('Quick notes') }}">
                            @foreach($noteSnippets as $s)
                                <button type="button"
                                        x-on:click="
                                            const ta = $refs.noteBody;
                                            const phrase = @js($s['text']);
                                            const current = ta.value.replace(/\s+$/, '');
                                            ta.value = current === '' ? phrase : current + '\n' + phrase;
                                            $wire.set('newNoteBody', ta.value, false);
                                            ta.focus();
                                            ta.setSelectionRange(ta.value.length, ta.value.length);
                                            statusNudge = @js($s['status']);
                                            if (statusNudge) { setTimeout(() => statusNudge = null, 15000) }
                                        "
                                        class="rounded-full px-2 py-0.5 text-[11px] text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800/50 ring-1 ring-inset ring-slate-300/60 dark:ring-slate-600/40 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                                    <span aria-hidden="true">+</span> {{ $s['text'] }}
                                </button>
                            @endforeach
                        </div>
                        <p class="mb-2 text-[11px] text-slate-400 dark:text-slate-500" x-show="statusNudge" x-cloak>
                            {{ __('Note ready — the matching status pill above is highlighted if you want to set it too.') }}
                        </p>
                        <textarea wire:model="newNoteBody" x-ref="noteBody" rows="2" maxlength="5000" placeholder="{{ __('Add a short note…') }}"
                                  class="block w-full rounded-lg border-slate-300 py-3 px-4 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                        @error('newNoteBody') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        <div class="mt-2 flex justify-end">
                            <button type="submit"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait"
                                class="rounded-lg bg-slate-900 dark:bg-slate-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors">{{ __('Add note') }}</button>
                        </div>
                    </form>
                @endauth
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
