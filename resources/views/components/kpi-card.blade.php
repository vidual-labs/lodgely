@props([
    'label' => '',
    'value' => 0,
    'tone'  => 'slate',
])

@php
    $tones = [
        'slate'   => ['text' => 'text-slate-900 dark:text-slate-100',    'bar' => 'bg-slate-300 dark:bg-slate-600'],
        'blue'    => ['text' => 'text-blue-700 dark:text-blue-400',       'bar' => 'bg-blue-500'],
        'rose'    => ['text' => 'text-rose-700 dark:text-rose-400',       'bar' => 'bg-rose-500'],
        'amber'   => ['text' => 'text-amber-700 dark:text-amber-400',     'bar' => 'bg-amber-500'],
        'emerald' => ['text' => 'text-emerald-700 dark:text-emerald-400', 'bar' => 'bg-emerald-500'],
    ];
    $tone_conf = $tones[$tone] ?? $tones['slate'];
@endphp

<div class="relative rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-4 overflow-hidden shadow-sm">
    <div class="absolute top-0 inset-x-0 h-0.5 {{ $tone_conf['bar'] }}"></div>
    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 font-medium">{{ $label }}</div>
    <div class="mt-2 text-2xl font-semibold {{ $tone_conf['text'] }} tabular-nums">{{ number_format($value) }}</div>
</div>
