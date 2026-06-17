@php
    /** @var \Illuminate\Support\Collection $rows */
    /** @var array<int, \App\Domain\Reporting\Enums\ReportColumn> $columns */
@endphp

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="metrics-table" style="border-collapse: collapse; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; min-width:480px;">
    <thead>
        <tr style="background-color:#f8fafc;">
            <th align="left" style="padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color:#64748b; border-bottom:1px solid #e2e8f0;">
                {{ __('Month') }}
            </th>
            @foreach($columns as $col)
                <th align="right" style="padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color:#64748b; border-bottom:1px solid #e2e8f0;">
                    {{ $col->label() }}
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td align="left" style="padding: 10px 12px; font-size: 13px; color:#0f172a; border-bottom:1px solid #f1f5f9;">
                    {{ \Carbon\Carbon::parse($row->month.'-01')->format('M Y') }}
                </td>
                @foreach($columns as $col)
                    <td align="right" style="padding: 10px 12px; font-size: 13px; color:#334155; border-bottom:1px solid #f1f5f9;">
                        {{ $col->format($row->{$col->value} ?? null) }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
