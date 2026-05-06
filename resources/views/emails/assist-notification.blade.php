@php
    $dir      = $lang === 'ar' ? 'rtl' : 'ltr';
    $align    = $lang === 'ar' ? 'right' : 'left';
    $greeting = $lang === 'ar' ? 'مرحبا' : 'Hello';
    $autoMsg  = $lang === 'ar'
        ? 'هذه رسالة آلية من DabApp Assist. إذا كان لديك أي استفسار، لا تتردد في التواصل معنا.'
        : 'This is an automated message from DabApp Assist. If you have any questions, feel free to contact us.';
    $platform = $lang === 'ar'
        ? 'منصة DabApp - خدمة المساعدة على الطريق'
        : 'DabApp Platform - Roadside Assistance';
    $rights   = $lang === 'ar' ? 'جميع الحقوق محفوظة.' : 'All rights reserved.';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
            direction: {{ $dir }};
            text-align: {{ $align }};
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,.1);
        }
        .header {
            background: linear-gradient(135deg, #f03d24 0%, #032c40 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .logo { max-width: 150px; height: auto; }
        .body { padding: 40px 30px; }
        .greeting {
            font-size: 18px;
            color: #032c40;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .card {
            background: #f9f9f9;
            border-{{ $align }}: 4px solid #f03d24;
            padding: 20px;
            margin: 25px 0;
            border-radius: 8px;
        }
        .card-title {
            font-size: 20px;
            color: #032c40;
            margin-bottom: 12px;
            font-weight: bold;
        }
        .card-body { color: #555555; font-size: 16px; line-height: 1.8; }
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #f03d24, transparent);
            margin: 30px 0;
        }
        .footer {
            background-color: #032c40;
            color: #ffffff;
            padding: 25px 30px;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">

        <div class="header">
            <img src="https://be.dabapp.co/LogoDabApp.png" alt="DabApp" class="logo">
        </div>

        <div class="body">
            <p class="greeting">
                {{ $greeting }}, <span dir="auto" style="unicode-bidi:isolate;">{{ $user->first_name }}</span>@if($lang === 'ar')&rlm;@endif!
            </p>

            <div class="card">
                <h2 class="card-title">{{ $title }}</h2>
                <p class="card-body">{!! nl2br(e($body)) !!}</p>
            </div>

            <div class="divider"></div>

            <p style="color:#666666; font-size:14px; text-align:center;">
                {{ $autoMsg }}
            </p>
        </div>

        <div class="footer">
            <p style="font-weight:600; margin-bottom:10px;">{{ $platform }}</p>
            <p style="font-size:12px; opacity:.8;">© {{ date('Y') }} DabApp. {{ $rights }}</p>
        </div>

    </div>
</body>
</html>
