<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#0f1c23;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f1c23;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">
          <tr>
            <td style="padding:0 4px 18px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;font-weight:800;letter-spacing:-0.02em;color:#e9f0ee;">
              {{ $app }}<span style="display:inline-block;width:9px;height:9px;margin-left:4px;background:{{ $accent }};border-radius:2px;"></span>
            </td>
          </tr>
          <tr>
            <td style="background:#172a33;border:1px solid #2c4753;border-radius:10px;padding:30px 28px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;color:#e9f0ee;">
              <h1 style="margin:0 0 14px;font-size:24px;line-height:1.2;font-weight:700;color:#e9f0ee;">{{ $heading }}</h1>
              <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#e9f0ee;">{{ $hello }}</p>
              <p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#c4d2d0;">
                {{ $body }}
              </p>
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="background:{{ $accent }};border-radius:7px;">
                    <a href="{{ $url }}" style="display:inline-block;padding:12px 22px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;color:{{ $ink }};text-decoration:none;">{{ $button }}</a>
                  </td>
                </tr>
              </table>
              <p style="margin:22px 0 6px;font-size:13px;line-height:1.6;color:#93a9ac;">
                {{ $valid }}
              </p>
              <p style="margin:0;font-family:Consolas,'Courier New',monospace;font-size:12px;line-height:1.5;color:#93a9ac;word-break:break-all;">{{ $url }}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 4px 0;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#93a9ac;">
              {{ $ignore }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
