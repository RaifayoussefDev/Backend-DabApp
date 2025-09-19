<!-- resources/views/emails/soom-REJECT.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SOOM مرفوض / SOOM Rejected</title>
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
            background-color: #00263a;
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
            font-size: 28px;
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
        .reason {
            background-color: #f8d7da;
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
            <h1>SOOM مرفوض / SOOM Rejected</h1>
        </div>
        <div class="accent-bar"></div>

        <div class="content english">
            <h2>Hello {{ $submission->user->first_name ?? 'Buyer' }},</h2>
            <p>Unfortunately, your SOOM has been rejected by the seller.</p>

            <div class="section">
                <h3>{{ $listing->title }}</h3>
                <p><strong>Rejected Amount:</strong> <span class="amount">{{ number_format($submission->amount, 2) }} SAR</span></p>
                <p><strong>Seller:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
            </div>

            @if($reason)
            <div class="reason">
                <h4>Rejection Reason:</h4>
                <p>{{ $reason }}</p>
            </div>
            @endif

            <p>Don't worry! You can submit a new SOOM if the listing is still available.</p>
        </div>

        <div class="content arabic">
            <h2>مرحباً {{ $submission->user->first_name ?? 'المشتري' }}،</h2>
            <p>للأسف، تم رفض عرض السوم الخاص بك من قبل البائع.</p>

            <div class="section">
                <h3>{{ $listing->title }}</h3>
                <p><strong>المبلغ المرفوض:</strong> <span class="amount">{{ number_format($submission->amount, 2) }} ريال</span></p>
                <p><strong>البائع:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
            </div>

            @if($reason)
            <div class="reason">
                <h4>سبب الرفض:</h4>
                <p>{{ $reason }}</p>
            </div>
            @endif

            <p>لا تقلق! يمكنك تقديم عرض سوم جديد إذا كان الإعلان لا يزال متاحاً.</p>
        </div>

        <div class="footer">
            <p style="margin: 10px 0 0 0; opacity: 0.8;">DabApp</p>

        </div>
    </div>
</body>
</html>
