@props([
    'column', // App\Domain\Reporting\Enums\ReportColumn
    'rows',   // Collection of row objects with ->month ("YYYY-MM") and ->{column->value}
])

@php
    $points = collect($rows)->map(fn ($r) => [
        'month' => $r->month,
        'value' => $r->{$column->value} ?? 0,
    ])->values();

    $values = $points->map(fn ($p) => (float) ($p['value'] ?? 0));
    $max    = (float) ($values->max() ?? 0);
    $count  = max(1, $points->count());

    // Arbitrary viewBox units; preserveAspectRatio="none" scales solid bars to fit.
    $slot = 10;            // horizontal slot per month
    $barW = 6;             // bar width within slot
    $vw   = $count * $slot;
    $vh   = 100;

    $peak = $points->sortByDesc('value')->first();
@endphp

<div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-4 shadow-sm">
    <div class="flex items-baseline justify-between mb-2 gap-2">
        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate">{{ $column->label() }}</span>
        <span class="text-xs text-slate-400 dark:text-slate-500 whitespace-nowrap">
            {{ __('peak') }} {{ $column->format($peak['value'] ?? null) }}
        </span>
    </div>

    @if($max <= 0)
        <div class="h-20 flex items-center justify-center text-xs text-slate-400 dark:text-slate-500">
            {{ __('No data') }}
        </div>
    @else
        <svg viewBox="0 0 {{ $vw }} {{ $vh }}" preserveAspectRatio="none"
             class="w-full h-20 text-brand-600 dark:text-brand-400" role="img"
             aria-label="{{ $column->label() }} {{ __('monthly trend') }}">
            @foreach($points as $i => $p)
                @php
                    $val = (float) ($p['value'] ?? 0);
                    $h   = $max > 0 ? ($val / $max) * ($vh - 4) : 0;
                    $x   = $i * $slot + ($slot - $barW) / 2;
                    $y   = $vh - max($h, 0.5);
                @endphp
                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barW }}" height="{{ max($h, 0.5) }}"
                      fill="currentColor" opacity="{{ $val > 0 ? '0.9' : '0.25' }}">
                    <title>{{ \Carbon\Carbon::createFromFormat('Y-m', $p['month'])->translatedFormat('M Y') }}: {{ $column->format($p['value']) }}</title>
                </rect>
            @endforeach
        </svg>
        <div class="mt-1 flex text-[10px] text-slate-400 dark:text-slate-500">
            @foreach($points as $p)
                <span class="flex-1 text-center truncate">{{ \Carbon\Carbon::createFromFormat('Y-m', $p['month'])->translatedFormat('M') }}</span>
            @endforeach
        </div>
    @endif
</div>
