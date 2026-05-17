<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $data['subject'] ?? 'Report' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color:#0f172a;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 1px 2px rgba(15,23,42,0.04);">
                    {{-- Header --}}
                    <tr>
                        <td style="padding: 20px 28px; border-bottom: 1px solid #e2e8f0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="font-size: 18px; font-weight: 600; color:#0f172a;">
                                        {{ config('lodgely.brand.name', 'lodgely') }}
                                    </td>
                                    <td align="right" style="font-size: 12px; color:#64748b;">
                                        {{ $data['period']['label'] ?? '' }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Greeting --}}
                    <tr>
                        <td style="padding: 24px 28px 0; font-size: 15px; line-height: 1.55; color:#0f172a;">
                            <p style="margin: 0 0 8px;">
                                {{ __('Hi :name,', ['name' => $data['recipient']->name]) }}
                            </p>
                            <p style="margin: 0; color:#475569;">
                                {{ __('Here is your :period report.', ['period' => $data['period']['label']]) }}
                            </p>
                        </td>
                    </tr>

                    {{-- Intro --}}
                    @if(! empty($data['intro_html']))
                        <tr>
                            <td style="padding: 16px 28px 0;">
                                <div style="font-size: 14px; line-height: 1.6; color:#334155;">
                                    {!! $data['intro_html'] !!}
                                </div>
                            </td>
                        </tr>
                    @endif

                    {{-- KPI strip --}}
                    @if($data['email']->include_kpi_strip && ! empty($data['totals']))
                        <tr>
                            <td style="padding: 20px 28px 0;">
                                @include('mail.partials.kpi', ['totals' => $data['totals'], 'columns' => $data['columns']])
                            </td>
                        </tr>
                    @endif

                    {{-- Monthly metrics table --}}
                    @if($data['email']->include_metrics_table && $data['rows']->isNotEmpty() && ! empty($data['columns']))
                        <tr>
                            <td style="padding: 20px 28px 0;">
                                @include('mail.partials.metrics-table', ['rows' => $data['rows'], 'columns' => $data['columns']])
                            </td>
                        </tr>
                    @endif

                    {{-- AI summary --}}
                    @if($data['email']->include_ai_summary && $data['ai_summary'] && $data['ai_summary']->response)
                        <tr>
                            <td style="padding: 20px 28px 0;">
                                @include('mail.partials.ai-summary', ['summary' => $data['ai_summary']])
                            </td>
                        </tr>
                    @endif

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 28px 28px 24px;">
                            <hr style="border:none; border-top: 1px solid #e2e8f0; margin: 0 0 16px;">
                            <p style="margin: 0; font-size: 12px; color:#94a3b8; line-height: 1.5;">
                                {{ __('You are receiving this email because :brand sends scheduled report updates to your account.', ['brand' => config('lodgely.brand.name', 'lodgely')]) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
