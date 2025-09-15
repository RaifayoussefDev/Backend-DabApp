<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SOOM Reçu / SOOM Received</title>
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
            <h1>SOOM جديد مُستلم / New SOOM Received</h1>
        </div>
        <div class="accent-bar"></div>

        <div class="content english">
            <h2>Hello {{ $listing->seller->first_name ?? 'Seller' }},</h2>
            <p>You have received a new SOOM submission for your listing:</p>

            <div class="section">
                <h3>{{ $listing->title }}</h3>
                <p><strong>SOOM Amount:</strong> <span class="amount">{{ number_format($submission->amount, 2) }} SAR</span></p>
                <p><strong>From:</strong> {{ $buyer->first_name }} {{ $buyer->last_name }}</p>
                <p><strong>Submission Date:</strong> {{ $submission->submission_date->format('M d, Y H:i') }}</p>
            </div>

            <p>You can accept or reject this SOOM from your dashboard.</p>
        </div>

        <div class="content arabic">
            <h2>مرحباً {{ $listing->seller->first_name ?? 'البائع' }}،</h2>
            <p>لقد تلقيت عرض سوم جديد لإعلانك:</p>

            <div class="section">
                <h3>{{ $listing->title }}</h3>
                <p><strong>مبلغ السوم:</strong> <span class="amount">{{ number_format($submission->amount, 2) }} ريال</span></p>
                <p><strong>من:</strong> {{ $buyer->first_name }} {{ $buyer->last_name }}</p>
                <p><strong>تاريخ التقديم:</strong> {{ $submission->submission_date->format('M d, Y H:i') }}</p>
            </div>

            <p>يمكنك قبول أو رفض هذا السوم من لوحة التحكم الخاصة بك.</p>
        </div>

        <div class="footer">
            <p style="margin: 10px 0 0 0; opacity: 0.8;">DabApp</p>
        </div>
    </div>
</body>
</html>
