@props(['summary'])

<div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 px-4 py-3 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div>
            <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('AI summary') }}
                @if($summary->period_start)
                    · {{ \Carbon\Carbon::parse($summary->period_start)->translatedFormat('M Y') }} → {{ \Carbon\Carbon::parse($summary->period_end)->translatedFormat('M Y') }}
                @endif
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Reviewed by') }} {{ $summary->operator?->name ?? '—' }} · {{ $summary->approved_at?->diffForHumans() }}
            </div>
        </div>
        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $summary->status->badgeClasses() }}">{{ $summary->status->label() }}</span>
    </div>
    <div class="mt-3 text-sm whitespace-pre-wrap text-slate-800 dark:text-slate-200">{{ $summary->response }}</div>
</div>
