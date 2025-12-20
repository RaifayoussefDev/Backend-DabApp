<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $notification->title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: linear-gradient(135deg, {{ $primaryColor }} 0%, {{ $secondaryColor }} 100%);
            padding: 30px 20px;
            text-align: center;
        }

        .logo {
            max-width: 150px;
            height: auto;
            margin-bottom: 15px;
        }

        .header-title {
            color: #ffffff;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .email-body {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            color: {{ $secondaryColor }};
            margin-bottom: 20px;
            font-weight: 600;
        }

        .notification-card {
            background-color: #f9f9f9;
            border-left: 4px solid {{ $primaryColor }};
            padding: 20px;
            margin: 25px 0;
            border-radius: 8px;
        }

        .notification-title {
            font-size: 20px;
            color: {{ $secondaryColor }};
            margin-bottom: 12px;
            font-weight: bold;
        }

        .notification-message {
            color: #555555;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .notification-icon {
            display: inline-block;
            width: 40px;
            height: 40px;
            background-color: {{ $primaryColor }};
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            color: white;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, {{ $primaryColor }} 0%, #d63519 100%);
            color: #ffffff;
            padding: 14px 35px;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(240, 61, 36, 0.3);
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(240, 61, 36, 0.4);
        }

        .info-box {
            background-color: #e8f4f8;
            border-left: 4px solid {{ $secondaryColor }};
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
        }

        .info-box p {
            margin: 5px 0;
            color: #555;
            font-size: 14px;
        }

        .info-box strong {
            color: {{ $secondaryColor }};
        }

        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, {{ $primaryColor }}, transparent);
            margin: 30px 0;
        }

        .email-footer {
            background-color: {{ $secondaryColor }};
            color: #ffffff;
            padding: 25px 30px;
            text-align: center;
            font-size: 14px;
        }

        .footer-links {
            margin: 15px 0;
        }

        .footer-links a {
            color: #ffffff;
            text-decoration: none;
            margin: 0 10px;
            opacity: 0.9;
        }

        .footer-links a:hover {
            opacity: 1;
            text-decoration: underline;
        }

        .social-icons {
            margin: 20px 0;
        }

        .social-icons a {
            display: inline-block;
            width: 35px;
            height: 35px;
            background-color: {{ $primaryColor }};
            border-radius: 50%;
            text-align: center;
            line-height: 35px;
            color: white;
            margin: 0 5px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .social-icons a:hover {
            background-color: #d63519;
        }

        .timestamp {
            color: #999999;
            font-size: 13px;
            margin-top: 20px;
            text-align: center;
        }

        .priority-high {
            background-color: #fff3cd;
            border-left-color: #ffc107;
        }

        .priority-urgent {
            background-color: #f8d7da;
            border-left-color: #dc3545;
        }

        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }

            .email-body {
                padding: 25px 20px;
            }

            .header-title {
                font-size: 20px;
            }

            .notification-title {
                font-size: 18px;
            }

            .action-button {
                padding: 12px 25px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <img src="https://be.dabapp.co/LogoDabApp.png" alt="DabApp Logo" class="logo">
        </div>

        <!-- Body -->
        <div class="email-body">
            <p class="greeting">مرحبا {{ $userName }}! 👋</p>

            <div class="notification-card @if($notification->priority === 'high') priority-high @elseif($notification->priority === 'urgent') priority-urgent @endif">
                @if($notification->icon)
                    <div class="notification-icon">
                        {{ $notification->icon === 'check_circle' ? '✓' : '' }}
                        {{ $notification->icon === 'cancel' ? '✗' : '' }}
                        {{ $notification->icon === 'info' ? 'ℹ' : '' }}
                        {{ $notification->icon === 'gavel' ? '🔨' : '' }}
                        {{ $notification->icon === 'celebration' ? '🎉' : '' }}
                        {{ $notification->icon === 'payment' ? '💳' : '' }}
                        {{ $notification->icon === 'message' ? '💬' : '' }}
                        {{ $notification->icon === 'event' ? '📅' : '' }}
                    </div>
                @endif

                <h2 class="notification-title">{{ $notification->title }}</h2>

                <div class="notification-message">
                    {!! nl2br(e($emailContent)) !!}
                </div>

                @if(!empty($data))
                    <div class="info-box">
                        @foreach($data as $key => $value)
                            @if(!in_array($key, ['listing_id', 'transaction_id', 'event_id']))
                                <p><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</p>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if($actionUrl)
                    <center>
                        <a href="{{ $actionUrl }}" class="action-button">
                            عرض التفاصيل
                        </a>
                    </center>
                @endif
            </div>

            <div class="divider"></div>

            <p style="color: #666; font-size: 14px; text-align: center;">
                هذه رسالة آلية من DabApp. إذا كان لديك أي استفسار، لا تتردد في الاتصال بنا.
            </p>

            <div class="timestamp">
                تم الإرسال في {{ $notification->created_at->format('d/m/Y H:i') }}
            </div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p style="margin-bottom: 15px; font-weight: 600;">منصة DabApp - سوق الدراجات النارية</p>


            <!-- <div class="footer-links">
                <a href="{{ config('app.url') }}">الرئيسية</a>
                <a href="{{ config('app.url') }}/contact">اتصل بنا</a>
                <a href="{{ config('app.url') }}/privacy">سياسة الخصوصية</a>
                <a href="{{ config('app.url') }}/settings/notifications">إدارة الإشعارات</a>
            </div> -->

            <p style="margin-top: 20px; font-size: 12px; opacity: 0.8;">
                © {{ date('Y') }} DabApp. جميع الحقوق محفوظة.
            </p>

            <p style="margin-top: 10px; font-size: 11px; opacity: 0.7;">
                لإلغاء الاشتراك في رسائل البريد الإلكتروني،
                <a href="{{ config('app.url') }}/settings/notifications" style="color: #ffffff;">انقر هنا</a>
            </p>
        </div>
    </div>
</body>
</html>
