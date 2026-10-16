<!-- resources/views/emails/soom-accepted.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SOOM مقبول / SOOM Accepted</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            color: #00263a;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #00263a 0%, #f03d24 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .accent-bar {
            background-color: #f03d24;
            height: 4px;
        }
        .content {
            padding: 30px;
        }
        .amount {
            font-size: 32px;
            font-weight: bold;
            color: #f03d24;
        }
        .section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #f03d24;
        }
        .contact-info {
            background: linear-gradient(45deg, #e7f3ff 0%, #f0f8ff 100%);
            border: 2px solid #f03d24;
            padding: 25px;
            border-radius: 8px;
            margin: 25px 0;
        }
        .contact-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #f03d24;
            box-shadow: 0 2px 4px rgba(240, 61, 36, 0.1);
        }
        .warning {
            background-color: #fff3cd;
            border: 2px solid #f03d24;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .arabic {
            direction: rtl;
            text-align: right;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #f03d24;
        }
        .english {
            direction: ltr;
            text-align: left;
        }
        .footer {
            background-color: #00263a;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 SOOM مقبول / SOOM Accepted!</h1>
        </div>
        <div class="accent-bar"></div>

        <div class="content english">
            <h2>Congratulations {{ $submission->user->first_name ?? 'Buyer' }}!</h2>
            <p>Your SOOM has been accepted by the seller.</p>

            <div class="section">
                <h3>{{ $listing->title }}</h3>
                <p><strong>Accepted Amount:</strong> <span class="amount">{{ number_format($submission->amount, 2) }} {{ $listing->currencyCode() }}</span></p>
                <p><strong>Seller:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
                @if($submission->acceptance_date)
                    <p><strong>Acceptance Date:</strong> {{ $submission->acceptance_date->format('M d, Y H:i') }}</p>
                @else
                    <p><strong>Acceptance Date:</strong> {{ now()->format('M d, Y H:i') }}</p>
                @endif
            </div>

            <!-- NOUVELLE SECTION: Contact Information Exchange -->
            <div class="contact-info">
                <h3>📞 Contact Information / معلومات الاتصال</h3>
                <p><strong>You can now contact each other directly to arrange the transaction:</strong></p>

                <div class="contact-card">
                    <h4>🛍️ Seller Contact Details:</h4>
                    <p><strong>Name:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
                    <p><strong>Email:</strong> <a href="mailto:{{ $seller->email }}" style="color: #f03d24;">{{ $seller->email }}</a></p>
                    @if($seller->phone)
                    <p><strong>Phone:</strong> <a href="tel:{{ $seller->phone }}" style="color: #f03d24;">{{ $seller->phone }}</a></p>
                    @endif
                </div>

                <div class="contact-card">
                    <h4>🛒 Buyer Contact Details:</h4>
                    <p><strong>Name:</strong> {{ $submission->user->first_name }} {{ $submission->user->last_name }}</p>
                    <p><strong>Email:</strong> <a href="mailto:{{ $submission->user->email }}" style="color: #f03d24;">{{ $submission->user->email }}</a></p>
                    @if($submission->user->phone)
                    <p><strong>Phone:</strong> <a href="tel:{{ $submission->user->phone }}" style="color: #f03d24;">{{ $submission->user->phone }}</a></p>
                    @endif
                </div>
            </div>

            <div class="warning">
                <h4>⚠️ Important Notice</h4>
                <p>The seller has <strong>5 days</strong> to validate the sale. After validation, the transaction will be finalized.</p>
                @if($submission->acceptance_date)
                    <p><strong>Validation Deadline:</strong> {{ $submission->acceptance_date->addDays(5)->format('M d, Y H:i') }}</p>
                @else
                    <p><strong>Validation Deadline:</strong> {{ now()->addDays(5)->format('M d, Y H:i') }}</p>
                @endif
            </div>
        </div>

        <div class="content arabic">
            <h2>تهانينا {{ $submission->user->first_name ?? 'المشتري' }}!</h2>
            <p>تم قبول عرض السوم الخاص بك من قبل البائع.</p>

            <div class="section">
                <h3>{{ $listing->title }}</h3>
                <p><strong>المبلغ المقبول:</strong> <span class="amount">{{ number_format($submission->amount, 2) }} {{ $listing->currencyNameAr() }}</span></p>
                <p><strong>البائع:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
                @if($submission->acceptance_date)
                    <p><strong>تاريخ القبول:</strong> {{ $submission->acceptance_date->format('M d, Y H:i') }}</p>
                @else
                    <p><strong>تاريخ القبول:</strong> {{ now()->format('M d, Y H:i') }}</p>
                @endif
            </div>

            <!-- معلومات الاتصال بالعربية -->
            <div class="contact-info">
                <h3>📞 معلومات الاتصال</h3>
                <p><strong>يمكنكما الآن التواصل مباشرة لترتيب المعاملة:</strong></p>

                <div class="contact-card">
                    <h4>🛍️ تفاصيل اتصال البائع:</h4>
                    <p><strong>الاسم:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
                    <p><strong>البريد الإلكتروني:</strong> <a href="mailto:{{ $seller->email }}" style="color: #f03d24;">{{ $seller->email }}</a></p>
                    @if($seller->phone)
                    <p><strong>الهاتف:</strong> <a href="tel:{{ $seller->phone }}" style="color: #f03d24;">{{ $seller->phone }}</a></p>
                    @endif
                </div>

                <div class="contact-card">
                    <h4>🛒 تفاصيل اتصال المشتري:</h4>
                    <p><strong>الاسم:</strong> {{ $submission->user->first_name }} {{ $submission->user->last_name }}</p>
                    <p><strong>البريد الإلكتروني:</strong> <a href="mailto:{{ $submission->user->email }}" style="color: #f03d24;">{{ $submission->user->email }}</a></p>
                    @if($submission->user->phone)
                    <p><strong>الهاتف:</strong> <a href="tel:{{ $submission->user->phone }}" style="color: #f03d24;">{{ $submission->user->phone }}</a></p>
                    @endif
                </div>
            </div>

            <div class="warning">
                <h4>⚠️ إشعار مهم</h4>
                <p>لدى البائع <strong>5 أيام</strong> للتحقق من صحة البيع. بعد التحقق، ستكتمل المعاملة.</p>
                @if($submission->acceptance_date)
                    <p><strong>الموعد النهائي للتحقق:</strong> {{ $submission->acceptance_date->addDays(5)->format('M d, Y H:i') }}</p>
                @else
                    <p><strong>الموعد النهائي للتحقق:</strong> {{ now()->addDays(5)->format('M d, Y H:i') }}</p>
                @endif
            </div>
        </div>

        <div class="footer">
            <p>DabApp</p>
        </div>
    </div>
</body>
</html>
