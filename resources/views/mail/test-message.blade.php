<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('SMTP test email') }}</title>
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0f172a; line-height:1.5;">
    <div style="max-width:480px; margin:0 auto; padding:32px 24px;">
        <h1 style="font-size:18px; margin:0 0 12px;">{{ __('SMTP test email') }} ✅</h1>
        <p style="margin:0 0 12px;">
            {{ __('If you are reading this, :app can send email through your configured SMTP server. Reporting emails and password resets will go out the same way.', ['app' => $appName]) }}
        </p>
        <p style="margin:0; color:#64748b; font-size:13px;">
            {{ __('Sent :time', ['time' => now()->toDayDateTimeString()]) }}
        </p>
    </div>
</body>
</html>
