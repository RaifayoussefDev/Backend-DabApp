<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رسالة تواصل جديدة</title>
</head>
<body style="margin:0;padding:20px;background:#f5f5f5;font-family:'Segoe UI',Tahoma,Arial,sans-serif;line-height:1.7;color:#101828;direction:rtl;text-align:right;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="background:#032c40;color:#ffffff;padding:20px 24px;font-size:18px;font-weight:bold;">
                رسالة جديدة من نموذج «تواصل معنا»
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 6px;"><strong>الاسم:</strong> {{ $submission->name }}</p>
                <p style="margin:0 0 6px;"><strong>البريد:</strong>
                    <a href="mailto:{{ $submission->email }}" style="color:#f03d24;" dir="ltr">{{ $submission->email }}</a>
                </p>
                <p style="margin:0 0 16px;color:#667085;font-size:13px;"><strong>التاريخ:</strong>
                    <span dir="ltr">{{ $submission->created_at?->timezone('Asia/Riyadh')->format('Y-m-d H:i') }}</span> (توقيت الرياض)
                </p>
                <div style="background:#f8fafc;border:1px solid #eaecf0;border-radius:10px;padding:16px;white-space:pre-line;">{{ $submission->message }}</div>
                <p style="margin:18px 0 0;color:#667085;font-size:13px;">
                    للرد: اضغط «رد» في بريدك — الرد يذهب مباشرة إلى العميل.
                    الرسالة محفوظة أيضاً في لوحة الإدارة (رسائل التواصل) برقم #{{ $submission->id }}.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
