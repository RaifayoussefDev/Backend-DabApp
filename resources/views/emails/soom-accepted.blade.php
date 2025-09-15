<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SOOM Accepted</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
        }
        .header {
            background-color: #28a745;
            color: white;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .content { padding: 20px 0; }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 SOOM Accepted!</h1>
        </div>

        <div class="content">
            <h2>Congratulations {{ $submission->user->first_name ?? 'Buyer' }}!</h2>
            <p>Your SOOM has been accepted by the seller.</p>

            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h3>{{ $listing->title }}</h3>
                <p><strong>Accepted Amount:</strong> <span class="amount">${{ number_format($submission->amount, 2) }}</span></p>
                <p><strong>Seller:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
                @if($submission->acceptance_date)
                    <p><strong>Acceptance Date:</strong> {{ $submission->acceptance_date->format('M d, Y H:i') }}</p>
                @else
                    <p><strong>Acceptance Date:</strong> {{ now()->format('M d, Y H:i') }}</p>
                @endif
            </div>

            <div class="warning">
                <h4>⚠️ Important Notice</h4>
                <p>The seller has <strong>5 days</strong> to validate the sale. After validation, you will receive contact information to complete the transaction.</p>
                @if($submission->acceptance_date)
                    <p><strong>Validation Deadline:</strong> {{ $submission->acceptance_date->addDays(5)->format('M d, Y H:i') }}</p>
                @else
                    <p><strong>Validation Deadline:</strong> {{ now()->addDays(5)->format('M d, Y H:i') }}</p>
                @endif
            </div>

            <p>Best regards,<br>The SOOM Team</p>
        </div>
    </div>
</body>
</html>
