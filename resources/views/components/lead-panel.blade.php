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
     calledNudge/mailedNudge — suggest, never write.

     Layout rule for this panel: the things a client *does* (reach out, say
     where the lead stands, write a note) stay open at the top; what they only
     read occasionally (ad attribution, AI, the audit trail) collapses into a
     native <details>. <details> is the collapse pattern used elsewhere in the
     app precisely because it needs no JS and survives Livewire morphs. --}}
<div class="fixed inset-0 z-40 flex"
     x-data="{
        calledNudge: false,
        mailedNudge: false,
        statusNudge: null,
        insertPhrase(phrase, status) {
            const ta = this.$refs.noteBody;
            const current = ta.value.replace(/\s+$/, '');
            ta.value = current === '' ? phrase : current + '\n' + phrase;
            this.$wire.set('newNoteBody', ta.value, false);
            ta.focus();
            ta.setSelectionRange(ta.value.length, ta.value.length);
            this.statusNudge = status;
            if (status) { setTimeout(() => this.statusNudge = null, 15000) }
        },
     }"
     x-trap.noscroll="true" x-on:keydown.escape.window="$wire.closePanel()">
    <div class="flex-1 bg-slate-900/40 dark:bg-black/50" wire:click="closePanel"></div>

    <aside role="dialog" aria-modal="true" aria-labelledby="lead-panel-title"
           class="w-full max-w-[520px] bg-white dark:bg-slate-900 shadow-xl dark:shadow-black/40 flex flex-col border-l border-slate-200 dark:border-slate-700/50">
        {{-- The header sits outside the scroll area, so the status badge here
             keeps the lead's current state on screen however far down someone
             has scrolled — including while the intake pills are collapsed. --}}
        <header class="border-b border-slate-200 dark:border-slate-700/50 px-5 py-4 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Lead #:id', ['id' => $lead->id]) }}</div>
                <h2 id="lead-panel-title" class="mt-0.5 text-lg font-semibold text-slate-900 dark:text-slate-50 truncate">{{ $lead->full_name ?? '—' }}</h2>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                    {{ $lead->client_name ?? '—' }} · {{ $lead->campaign_name ?? '—' }}
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $lead->status->badgeClasses() }}">
                    {{ $lead->status->label() }}
                </span>
                <button type="button" wire:click="closePanel" aria-label="{{ __('Close') }}"
                        class="text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">✕</button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">

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

            {{-- contact — the two ways to reach this person, and nothing else.
                 Source and received time are provenance, not actions, so they
                 sit on one muted line instead of two more <dl> rows. --}}
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
                </dl>
                <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">
                    {{ __('via :source · received :when', [
                        'source' => str_replace('_', ' ', $lead->source),
                        'when' => $lead->created_at?->format(\App\Support\Dates::FORMAT),
                    ]) }}
                </p>
            </section>

            {{-- status — one card for everything that says where this lead
                 stands. Outreach toggles and status pills used to be two cards
                 with two headings and three standing helper lines; they are the
                 same gesture ("tap a pill to record where this is"), so they
                 read as one block now.

                 Both operators and clients may change these on a lead they can
                 see; {@see toggleOutreach()} / {@see setStatus()} /
                 {@see setPriority()} on InboxPage do the actual scoping. --}}
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

                // Events are eager-loaded latest-first, so the first status
                // event is the most recent one — "how long has this offer been
                // sitting?" without another query or a new column.
                $lastStatusChange = $lead->events->firstWhere('type', 'lead.status_changed');

                // Outcome statuses stay on screen; intake ones collapse. Reviewed
                // is set automatically on open and Incomplete/Duplicate are
                // operator hygiene, so none of them is a daily click — but the
                // *current* status always shows, even when it is an intake one,
                // otherwise the visible row has no ✓ anywhere.
                $intakeOptions  = collect($statusGroups)->firstWhere('key', \App\Domain\Leads\Enums\LeadStatus::GROUP_INTAKE)['options'] ?? [];
                $outcomeOptions = collect($statusGroups)->firstWhere('key', \App\Domain\Leads\Enums\LeadStatus::GROUP_OUTCOME)['options'] ?? [];
                $currentValue   = $lead->status->value;
                $currentIntake  = collect($intakeOptions)->firstWhere('value', $currentValue);
                $visibleStatuses = $currentIntake ? array_merge([$currentIntake], $outcomeOptions) : $outcomeOptions;
                $collapsedStatuses = collect($intakeOptions)->reject(fn ($o) => $o['value'] === $currentValue)->values()->all();
                $autoStatuses = \App\Domain\Leads\Services\LeadStatusAutomation::AUTO_TARGETS;
            @endphp
            <section class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-slate-50/60 dark:bg-slate-800/30 p-3">
                <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2"
                    title="{{ __('Opening a lead marks it Reviewed; the first outreach pill marks it Pending. Set the rest yourself.') }}">
                    {{ __('Status') }}
                </h3>

                <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">{{ __('Outreach') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($outreach as $o)
                        @php $nudgeVar = $o['field'] === 'called_at' ? 'calledNudge' : ($o['field'] === 'mailed_at' ? 'mailedNudge' : null); @endphp
                        <button type="button"
                                wire:click="toggleOutreach({{ $lead->id }}, '{{ $o['field'] }}')"
                                @if($nudgeVar) x-bind:class="{ 'ring-2 ring-offset-1 dark:ring-offset-slate-900 animate-pulse': {{ $nudgeVar }} }" @endif
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-colors {{ $o['value'] ? $o['on'] : $o['off'] }}"
                                title="{{ $o['value'] ? __(':label · :when', ['label' => $o['label'], 'when' => $o['value']->format(\App\Support\Dates::FORMAT)]) : __('Mark as :label', ['label' => mb_strtolower($o['label'])]) }}">
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

                <p class="mt-3 text-[10px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">{{ __('Current status') }}</p>
                <div class="flex flex-wrap gap-1">
                    @foreach($visibleStatuses as $o)
                        <x-lead-status-pill :lead="$lead" :option="$o" :current="$lead->status->value === $o['value']" :auto="in_array($o['value'], $autoStatuses, true)" />
                    @endforeach
                </div>

                <div class="mt-2 flex flex-wrap items-start justify-between gap-2">
                    @if($collapsedStatuses !== [])
                        <details class="text-[11px]">
                            <summary class="cursor-pointer select-none text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">{{ __('Intake') }}</summary>
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                @foreach($collapsedStatuses as $o)
                                    <x-lead-status-pill :lead="$lead" :option="$o" :auto="in_array($o['value'], $autoStatuses, true)" />
                                @endforeach
                            </div>
                        </details>
                    @else
                        <span></span>
                    @endif

                    <div class="flex items-center gap-1.5">
                        <label class="text-[11px] text-slate-500 dark:text-slate-400" for="lead-priority">{{ __('Priority') }}</label>
                        <select id="lead-priority" wire:change="setPriority({{ $lead->id }}, $event.target.value)"
                                class="rounded-lg border-slate-300 py-1.5 px-2.5 text-xs focus:border-brand-500 focus:ring-brand-500">
                            @foreach($priorityOptions as $o)
                                <option value="{{ $o['value'] }}" @selected($lead->priority->value === $o['value'])>{{ $o['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($lastStatusChange)
                    <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">
                        {{ __('Status set :when', ['when' => \App\Support\Dates::relativeOrExact($lastStatusChange->created_at)]) }}
                    </p>
                @endif
            </section>

            {{-- message --}}
            @if($lead->message)
                <section>
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Message') }}</h3>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/50 px-3 py-2 text-sm text-slate-800 dark:text-slate-200 whitespace-pre-wrap">{{ $lead->message }}</div>
                </section>
            @endif

            {{-- custom questions (form Q&A from Meta or other lead forms) — the
                 lead's own words, same as Message, so they stay open. --}}
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

            {{-- notes — moved above the attribution/AI blocks: writing a note is
                 an action, the sections below it are reference material. --}}
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
                    @php
                        // Plain contact phrases stay on screen — they're the ones
                        // used daily. Outcome-reason phrases (they just restate a
                        // status the pills below already carry) collapse.
                        $primarySnippets = array_values(array_filter($noteSnippets, fn ($s) => $s['status'] === null));
                        $moreSnippets    = array_values(array_filter($noteSnippets, fn ($s) => $s['status'] !== null));
                    @endphp
                    <form wire:submit.prevent="addNote" class="mt-3">
                        {{-- Quick phrases. insertPhrase() (on the panel's root
                             x-data) writes the textarea directly and pushes the
                             value to Livewire deferred ($wire.set(…, false)) —
                             a wire:click round-trip here is exactly the morph-layer
                             drop documented in CLAUDE.md, and a deferred set also
                             avoids clobbering whatever the user is mid-way through
                             typing. Snippets that imply an outcome light up the
                             matching status pill instead of writing it. --}}
                        <div class="mb-2 flex flex-wrap gap-1" role="group" aria-label="{{ __('Quick notes') }}">
                            @foreach($primarySnippets as $s)
                                <button type="button"
                                        x-on:click="insertPhrase(@js($s['text']), @js($s['status']))"
                                        class="rounded-full px-2 py-0.5 text-[11px] text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800/50 ring-1 ring-inset ring-slate-300/60 dark:ring-slate-600/40 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                                    <span aria-hidden="true">+</span> {{ $s['text'] }}
                                </button>
                            @endforeach
                            @if($moreSnippets !== [])
                                <details class="text-[11px]">
                                    <summary class="cursor-pointer select-none rounded-full px-2 py-0.5 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">{{ __('More phrases') }}</summary>
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        @foreach($moreSnippets as $s)
                                            <button type="button"
                                                    x-on:click="insertPhrase(@js($s['text']), @js($s['status']))"
                                                    class="rounded-full px-2 py-0.5 text-[11px] text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800/50 ring-1 ring-inset ring-slate-300/60 dark:ring-slate-600/40 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                                                <span aria-hidden="true">+</span> {{ $s['text'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
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

            {{-- ad source (Meta Lead Ads attribution) — reference material, so
                 it collapses. --}}
            @if($lead->source === 'meta_ads' || $lead->meta_lead_id || $lead->ad_id || $lead->form_id)
                <details class="group">
                    <summary class="cursor-pointer select-none text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300">{{ __('Ad source') }}</summary>
                    <dl class="mt-2 grid grid-cols-3 gap-y-2 text-sm">
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
                </details>
            @endif

            {{-- AI evaluation (operator only) — collapsed until there is
                 something to read. --}}
            @if(config('lodgely.ai.enabled') && auth()->user()?->isOperator())
                <details @if($aiSummary) open @endif>
                    <summary class="cursor-pointer select-none text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300">{{ __('AI evaluation') }}</summary>
                    <div class="mt-2">
                        @if($aiSummary)
                            <x-ai.summary-card :summary="$aiSummary" />
                        @else
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('No approved AI evaluation yet. Run one and review it in AI drafts.') }}</p>
                        @endif
                        <button type="button" wire:click="evaluateLeadWithAi({{ $lead->id }})"
                                class="mt-2 text-xs text-brand-600 dark:text-brand-400 hover:underline">
                            {{ __('Run AI evaluation') }}
                        </button>
                    </div>
                </details>
            @endif

            {{-- audit trail. Timestamps go exact once an event is over a day
                 old — "3 weeks ago" is no answer to "when did we call them?" —
                 with the full stamp on hover either way. Only the five most
                 recent stay on screen; the rest are one click away. --}}
            @php
                $events = $lead->events->take(20);
                $recentEvents = $events->take(5);
                $olderEvents = $events->slice(5);
            @endphp
            <section>
                <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Activity') }}</h3>
                <ul class="space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                    @foreach($recentEvents as $event)
                        <li class="flex items-center justify-between gap-2">
                            <span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ str_replace(['lead.', '_'], ['', ' '], $event->type) }}</span>
                                @if($event->user) · {{ $event->user->name }} @endif
                            </span>
                            <span class="shrink-0 text-slate-400 dark:text-slate-500 tabular-nums"
                                  title="{{ $event->created_at?->format(\App\Support\Dates::FORMAT) }}">{{ \App\Support\Dates::relativeOrExact($event->created_at) }}</span>
                        </li>
                    @endforeach
                </ul>
                @if($olderEvents->isNotEmpty())
                    <details class="mt-1.5">
                        <summary class="cursor-pointer select-none text-[11px] text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">{{ __('Show :count more', ['count' => $olderEvents->count()]) }}</summary>
                        <ul class="mt-1.5 space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                            @foreach($olderEvents as $event)
                                <li class="flex items-center justify-between gap-2">
                                    <span>
                                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ str_replace(['lead.', '_'], ['', ' '], $event->type) }}</span>
                                        @if($event->user) · {{ $event->user->name }} @endif
                                    </span>
                                    <span class="shrink-0 text-slate-400 dark:text-slate-500 tabular-nums"
                                          title="{{ $event->created_at?->format(\App\Support\Dates::FORMAT) }}">{{ \App\Support\Dates::relativeOrExact($event->created_at) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>
        </div>
    </aside>
</div>
