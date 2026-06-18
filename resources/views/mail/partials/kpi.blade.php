@php
    /** @var array<string, mixed> $totals */
    /** @var array<int, \App\Domain\Reporting\Enums\ReportColumn> $columns */
    /** @var string $currency */
    $currency = $currency ?? 'USD';
    $cards = collect($columns)->take(6)->values();
@endphp

{{-- Two cards per row on desktop; the .kpi-cell media rule stacks them to a
     single column on phones so nothing gets squeezed below ~150px wide. --}}
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    @foreach($cards->chunk(2) as $pair)
        <tr>
            @foreach($pair as $col)
                <td class="kpi-cell" width="50%" valign="top" style="padding: 4px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="kpi-card" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
                        <tr>
                            <td style="padding: 12px 14px;">
                                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color:#64748b; margin-bottom: 4px;">
                                    {{ $col->label() }}
                                </div>
                                <div style="font-size: 18px; font-weight: 600; color:#0f172a;">
                                    {{ $col->format($totals[$col->value] ?? null, $currency) }}
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            @endforeach
            @if($pair->count() === 1)
                <td width="50%" style="padding: 4px;">&nbsp;</td>
            @endif
        </tr>
    @endforeach
</table>
