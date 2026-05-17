@php
    /** @var \App\Models\AiSummary $summary */
    $paragraphs = preg_split("/\r?\n\r?\n/", trim((string) $summary->response)) ?: [];
@endphp

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#eef2ff; border-radius:10px;">
    <tr>
        <td style="padding: 16px 18px;">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color:#4f46e5; margin-bottom: 8px; font-weight: 600;">
                {{ __('Summary') }}
            </div>
            @foreach($paragraphs as $p)
                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.55; color:#1e1b4b;">
                    {{ $p }}
                </p>
            @endforeach
        </td>
    </tr>
</table>
