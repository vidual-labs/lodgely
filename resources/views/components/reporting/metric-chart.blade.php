@props([
    'column', // App\Domain\Reporting\Enums\ReportColumn
    'rows',   // Collection of row objects with ->month ("YYYY-MM") and ->{column->value}
])

@php
    // Thin adapter: turn a reporting view's monthly rows for one column into
    // the generic trend-chart point shape. The modern area chart itself lives
    // in <x-reporting.trend-chart>.
    $points = collect($rows)->map(function ($r) use ($column) {
        $value = $r->{$column->value} ?? 0;

        return [
            'label'   => \Carbon\Carbon::createFromFormat('Y-m', $r->month)->translatedFormat('M Y'),
            'value'   => (float) $value,
            'display' => $column->format($value),
        ];
    })->values();

    $peak = collect($rows)->sortByDesc(fn ($r) => $r->{$column->value} ?? 0)->first();
    $peakDisplay = $peak ? $column->format($peak->{$column->value} ?? null) : null;
@endphp

<x-reporting.trend-chart
    :title="$column->label()"
    :subtitle="$peakDisplay ? __('peak').' '.$peakDisplay : null"
    :points="$points"
    tone="brand"
/>
