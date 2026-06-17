@props([
    'title'    => '',          // chart heading
    'subtitle' => null,        // small muted text on the right (e.g. "peak …")
    'points'   => [],          // array<int, array{label:string, value:float|int, display:string}>
    'tone'     => 'brand',     // brand|blue|emerald|amber|rose|slate
])

@php
    // TradingView-ish area chart, drawn server-side as SVG with an Alpine
    // overlay for the hover crosshair + tooltip. No JS chart lib, no build
    // step — just SVG + a sprinkle of Alpine (server-rendered first rail).

    $tones = [
        'brand'   => '#6366f1',
        'blue'    => '#3b82f6',
        'emerald' => '#10b981',
        'amber'   => '#f59e0b',
        'rose'    => '#f43f5e',
        'slate'   => '#64748b',
    ];
    $stroke = $tones[$tone] ?? $tones['brand'];
    $uid    = 'tc'.substr(md5($title.$tone.uniqid('', true)), 0, 8);

    $pts = collect($points)->map(fn ($p) => [
        'label'   => (string) ($p['label'] ?? ''),
        'value'   => (float) ($p['value'] ?? 0),
        'display' => (string) ($p['display'] ?? ($p['value'] ?? '')),
    ])->values();

    $n      = $pts->count();
    $values = $pts->pluck('value');
    $max    = (float) ($values->max() ?? 0);
    $min    = (float) ($values->min() ?? 0);

    // Adaptive vertical range with a little headroom/footroom so a flat-ish
    // series still uses the canvas (TradingView never pins the line to the edge).
    $span = $max - $min;
    if ($span <= 0) {
        $span = $max > 0 ? $max : 1;
    }
    $lo = $min - $span * 0.12;
    $hi = $max + $span * 0.12;
    if ($hi - $lo <= 0) {
        $hi = $lo + 1;
    }

    $W = 320.0;  // viewBox width
    $H = 96.0;   // viewBox height

    // Normalised coordinates (0..1) used by the Alpine overlay, plus pixel
    // coordinates (0..W / 0..H) used to build the SVG path.
    $coords = [];
    foreach ($pts as $i => $p) {
        $xr = $n <= 1 ? 0.5 : $i / ($n - 1);
        $yr = ($hi - $p['value']) / ($hi - $lo); // 0 = top
        $coords[] = [
            'x'       => round($xr * $W, 2),
            'y'       => round($yr * $H, 2),
            'xr'      => round($xr, 5),
            'yr'      => round($yr, 5),
            'label'   => $p['label'],
            'display' => $p['display'],
        ];
    }

    // Smooth the line with a Catmull-Rom → cubic-Bézier conversion.
    $linePath = '';
    $areaPath = '';
    if (count($coords) === 1) {
        $c = $coords[0];
        $linePath = "M0,{$c['y']} L{$W},{$c['y']}";
        $areaPath = "M0,{$c['y']} L{$W},{$c['y']} L{$W},{$H} L0,{$H} Z";
    } elseif (count($coords) > 1) {
        $linePath = 'M'.$coords[0]['x'].','.$coords[0]['y'];
        $cnt = count($coords);
        for ($i = 0; $i < $cnt - 1; $i++) {
            $p0 = $coords[max(0, $i - 1)];
            $p1 = $coords[$i];
            $p2 = $coords[$i + 1];
            $p3 = $coords[min($cnt - 1, $i + 2)];

            $c1x = $p1['x'] + ($p2['x'] - $p0['x']) / 6;
            $c1y = $p1['y'] + ($p2['y'] - $p0['y']) / 6;
            $c2x = $p2['x'] - ($p3['x'] - $p1['x']) / 6;
            $c2y = $p2['y'] - ($p3['y'] - $p1['y']) / 6;

            $linePath .= ' C'.round($c1x, 2).','.round($c1y, 2)
                .' '.round($c2x, 2).','.round($c2y, 2)
                .' '.round($p2['x'], 2).','.round($p2['y'], 2);
        }
        $areaPath = $linePath.' L'.$W.','.$H.' L0,'.$H.' Z';
    }

    $first = $coords[0]['label'] ?? '';
    $last  = $coords[count($coords) - 1]['label'] ?? '';
    $hasData = $values->contains(fn ($v) => $v != 0.0);
@endphp

<div class="rounded-xl border border-slate-200 dark:border-slate-700/50 bg-white dark:bg-slate-900 p-4 shadow-sm">
    <div class="flex items-baseline justify-between gap-2 mb-2">
        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate">{{ $title }}</span>
        @if($subtitle !== null)
            <span class="text-[11px] text-slate-400 dark:text-slate-500 whitespace-nowrap">{{ $subtitle }}</span>
        @endif
    </div>

    @if($n === 0 || ! $hasData)
        <div class="h-24 flex items-center justify-center text-xs text-slate-400 dark:text-slate-500">
            {{ __('No data') }}
        </div>
    @else
        <div
            x-data="{
                pts: @js($coords),
                active: -1,
                tipX: 0,
                onMove(e) {
                    const r = this.$refs.canvas.getBoundingClientRect();
                    if (r.width === 0 || this.pts.length === 0) return;
                    let ratio = (e.clientX - r.left) / r.width;
                    ratio = Math.max(0, Math.min(1, ratio));
                    this.active = Math.round(ratio * (this.pts.length - 1));
                    this.tipX = this.pts[this.active].xr * 100;
                },
            }"
            x-ref="canvas"
            @mousemove="onMove($event)"
            @mouseleave="active = -1"
            @touchstart.passive="onMove($event.touches[0])"
            @touchmove.passive="onMove($event.touches[0])"
            class="relative select-none"
            style="touch-action: pan-y;"
        >
            <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none"
                 class="w-full h-24 overflow-visible" role="img"
                 aria-label="{{ $title }} {{ __('trend') }}">
                <defs>
                    <linearGradient id="{{ $uid }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%"   stop-color="{{ $stroke }}" stop-opacity="0.28"></stop>
                        <stop offset="100%" stop-color="{{ $stroke }}" stop-opacity="0"></stop>
                    </linearGradient>
                </defs>
                <path d="{{ $areaPath }}" fill="url(#{{ $uid }})"></path>
                <path d="{{ $linePath }}" fill="none" stroke="{{ $stroke }}" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>
            </svg>

            {{-- Hover crosshair + dot (positioned by percentage over the canvas) --}}
            <template x-if="active >= 0">
                <div class="pointer-events-none absolute inset-0">
                    <div class="absolute top-0 bottom-0 w-px bg-slate-300 dark:bg-slate-600"
                         :style="`left:${pts[active].xr * 100}%`"></div>
                    <div class="absolute h-2.5 w-2.5 -ml-[5px] -mt-[5px] rounded-full border-2 bg-white dark:bg-slate-900"
                         style="border-color: {{ $stroke }};"
                         :style="`left:${pts[active].xr * 100}%; top:${pts[active].yr * 100}%`"></div>
                </div>
            </template>

            {{-- Floating tooltip --}}
            <template x-if="active >= 0">
                <div class="pointer-events-none absolute -top-1 z-10 -translate-x-1/2 -translate-y-full"
                     :style="`left:${Math.max(8, Math.min(92, tipX))}%`">
                    <div class="rounded-md bg-slate-900 dark:bg-slate-700 px-2 py-1 text-center shadow-lg whitespace-nowrap">
                        <div class="text-[10px] text-slate-300" x-text="pts[active].label"></div>
                        <div class="text-xs font-semibold text-white tabular-nums" x-text="pts[active].display"></div>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-1.5 flex justify-between text-[10px] text-slate-400 dark:text-slate-500">
            <span class="truncate">{{ $first }}</span>
            <span class="truncate">{{ $last }}</span>
        </div>
    @endif
</div>
