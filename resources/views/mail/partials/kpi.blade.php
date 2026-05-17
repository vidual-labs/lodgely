@php
    /** @var array<string, mixed> $totals */
    /** @var array<int, \App\Domain\Reporting\Enums\ReportColumn> $columns */
    $cards = collect($columns)->take(6);
@endphp

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        @foreach($cards as $i => $col)
            <td width="33%" valign="top" style="padding: 4px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
                    <tr>
                        <td style="padding: 12px 14px;">
                            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color:#64748b; margin-bottom: 4px;">
                                {{ $col->label() }}
                            </div>
                            <div style="font-size: 18px; font-weight: 600; color:#0f172a;">
                                {{ $col->format($totals[$col->value] ?? null) }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            @if(($i + 1) % 3 === 0 && $i + 1 < $cards->count())
                </tr><tr>
            @endif
        @endforeach
    </tr>
</table>
