<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<title>{{ __('mail.otp_subject', ['code' => $code]) }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F6F8;font-family:'IBM Plex Sans Arabic',Segoe UI,Tahoma,Arial,sans-serif;color:#0E1B2B;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6F8;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#FFFFFF;border:1px solid #D7DCE2;border-radius:12px;">
<tr><td style="padding:24px 28px 0 28px;font-size:20px;font-weight:700;">A-FIT · أي-فت</td></tr>
<tr><td style="padding:16px 28px 0 28px;font-size:16px;line-height:1.6;">{{ __('mail.otp_greeting') }}</td></tr>
<tr><td style="padding:8px 28px 0 28px;font-size:16px;line-height:1.6;">{{ __('mail.otp_intro', ['minutes' => $minutes]) }}</td></tr>
<tr><td style="padding:20px 28px 0 28px;">
  <div style="background:#0E1B2B;color:#FFFFFF;border-radius:10px;padding:18px;text-align:center;font-size:34px;font-weight:700;letter-spacing:0.35em;direction:ltr;">{{ $code }}</div>
</td></tr>
<tr><td style="padding:20px 28px 0 28px;font-size:14px;line-height:1.6;color:#5B6878;">{{ __('mail.otp_ignore') }}</td></tr>
<tr><td style="padding:16px 28px 24px 28px;font-size:12px;line-height:1.6;color:#5B6878;border-top:1px solid #D7DCE2;">{{ __('mail.otp_footer') }}</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
