<div class="space-y-6" wire:poll.5s>
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('AI drafts') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Review, approve, reject and share AI-generated summaries before they reach a client.') }}
            </p>
        </div>

        <div class="flex rounded-lg bg-slate-100 dark:bg-slate-800/80 p-0.5 text-xs font-medium gap-0.5">
            @foreach([
                'pending'  => __('Pending'),
                'approved' => __('Approved'),
                'shared'   => __('Shared'),
                'rejected' => __('Rejected'),
                'failed'   => __('Failed'),
                'all'      => __('All'),
            ] as $val => $label)
                <button wire:click="$set('filter', '{{ $val }}')"
                        class="px-3 py-1.5 rounded-md transition-colors {{ $filter === $val ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/50">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <th class="px-3 py-2.5">{{ __('Kind') }}</th>
                    <th class="px-3 py-2.5">{{ __('Subject') }}</th>
                    <th class="px-3 py-2.5">{{ __('Status') }}</th>
                    <th class="px-3 py-2.5">{{ __('Requested') }}</th>
                    <th class="px-3 py-2.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($rows as $r)
                    <tr wire:key="row-{{ $r->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-3 py-2.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $r->kind->label() }}</td>
                        <td class="px-3 py-2.5 text-sm text-slate-600 dark:text-slate-400">
                            @if($r->subject_type === \App\Models\ClientReportingView::class)
                                {{ __('Report view') }} #{{ $r->subject_id }}
                                @if($r->period_start) · {{ $r->period_start->format('Y-m-d') }} → {{ $r->period_end?->format('Y-m-d') }} @endif
                            @elseif($r->subject_type === \App\Models\Lead::class)
                                {{ __('Lead') }} #{{ $r->subject_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $r->status->badgeClasses() }}">
                                {{ $r->status->label() }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $r->requestedBy?->name ?? '—' }} · {{ $r->created_at?->diffForHumans() }}
                        </td>
                        <td class="px-3 py-2.5 text-right">
                            <button wire:click="select({{ $r->id }})" class="text-xs text-slate-700 dark:text-slate-300 hover:underline">{{ __('Open') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-12 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No drafts in this view.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($rows->hasPages())
            <div class="border-t border-slate-100 dark:border-slate-800 px-3 py-2">{{ $rows->links() }}</div>
        @endif
    </div>

    @if($selected)
        <div class="fixed inset-0 z-40 flex">
            <div class="flex-1 bg-slate-900/40 dark:bg-black/50" wire:click="close"></div>
            <aside class="w-full max-w-[640px] bg-white dark:bg-slate-900 shadow-xl flex flex-col border-l border-slate-200 dark:border-slate-700/50">
                <header class="border-b border-slate-200 dark:border-slate-700/50 px-5 py-4 flex items-start justify-between">
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('AI draft #:id', ['id' => $selected->id]) }}</div>
                        <h2 class="mt-0.5 text-lg font-semibold text-slate-900 dark:text-slate-50">{{ $selected->kind->label() }}</h2>
                        <div class="mt-1">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $selected->status->badgeClasses() }}">{{ $selected->status->label() }}</span>
                        </div>
                    </div>
                    <button type="button" wire:click="close" class="text-slate-400 hover:text-slate-700 transition-colors">✕</button>
                </header>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                    @if($selected->status?->value === 'failed' && $selected->error)
                        <div class="rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/50 px-3 py-2 text-sm text-rose-800 dark:text-rose-300">
                            <strong>{{ __('Error:') }}</strong> {{ $selected->error }}
                        </div>
                    @endif

                    @if($selected->response)
                        <section>
                            <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('AI response') }}</h3>
                            <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/50 px-3 py-2 text-sm whitespace-pre-wrap text-slate-800 dark:text-slate-200">{{ $selected->response }}</div>
                        </section>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('Waiting for the model to respond…') }}</p>
                    @endif

                    <section>
                        <h3 class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('Prompt sent') }}</h3>
                        <details class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/50 px-3 py-2 text-xs">
                            <summary class="cursor-pointer text-slate-600 dark:text-slate-400">{{ __('Show prompt') }}</summary>
                            <pre class="mt-2 whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $selected->prompt }}</pre>
                        </details>
                    </section>

                    @if($selected->token_usage)
                        <section class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Tokens — prompt: :p, completion: :c, total: :t', [
                                'p' => $selected->token_usage['prompt'] ?? '—',
                                'c' => $selected->token_usage['completion'] ?? '—',
                                't' => $selected->token_usage['total'] ?? '—',
                            ]) }}
                        </section>
                    @endif

                    @if(in_array($selected->status?->value, ['pending','failed','approved'], true))
                        <section class="space-y-3 border-t border-slate-100 dark:border-slate-800 pt-4">
                            <div class="flex flex-wrap gap-2">
                                @if($selected->status?->value === 'pending' && $selected->response)
                                    <button wire:click="approve({{ $selected->id }})"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                                        {{ __('Approve') }}
                                    </button>
                                @endif

                                @if($selected->status?->value === 'approved')
                                    <button wire:click="share({{ $selected->id }})"
                                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                                        {{ __('Share with client') }}
                                    </button>
                                @endif

                                <button wire:click="regenerate({{ $selected->id }})"
                                        class="rounded-lg px-3 py-1.5 text-sm border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                                    {{ __('Regenerate') }}
                                </button>
                            </div>

                            @if($selected->status?->value === 'pending' && $selected->response)
                                <div class="space-y-2">
                                    <textarea wire:model="rejectReason" rows="2" maxlength="400"
                                              placeholder="{{ __('Optional reason for rejection…') }}"
                                              class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                    <button wire:click="reject({{ $selected->id }})"
                                            class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-700">
                                        {{ __('Reject') }}
                                    </button>
                                </div>
                            @endif
                        </section>
                    @endif
                </div>
            </aside>
        </div>
    @endif
</div>
