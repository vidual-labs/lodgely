@props([
    'label' => '',
    'value' => 0,
    'tone'  => 'slate',
])

@php
    $tones = [
        'slate' => 'text-slate-900',
        'blue'  => 'text-blue-700',
        'rose'  => 'text-rose-700',
        'amber' => 'text-amber-800',
        'emerald' => 'text-emerald-800',
    ];
    $cls = $tones[$tone] ?? $tones['slate'];
@endphp

<div class="rounded-md border border-slate-200 bg-white p-4">
    <div class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold {{ $cls }} tabular-nums">{{ number_format($value) }}</div>
</div>
