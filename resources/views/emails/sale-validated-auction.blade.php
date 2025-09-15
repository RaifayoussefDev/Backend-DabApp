<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>البيع مؤكد / Sale Validated</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            color: #00263a;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #00263a 0%, #f03d24 100%);
            color: white;
            padding: 25px;
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
            font-size: 36px;
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
        .important {
            background-color: #fff3cd;
            border: 2px solid #f03d24;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .safety {
            background-color: #d1ecf1;
            border: 2px solid #00263a;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .arabic {
            direction: rtl;
            text-align: right;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 3px solid #f03d24;
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
        .contact-flex {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .contact-flex > div {
            width: 48%;
            min-width: 250px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 البيع مؤكد / Sale Validated!</h1>
        </div>
        <div class="accent-bar"></div>

        <div class="content english">
            <h2>Congratulations! The sale has been validated.</h2>

            <div class="section">
                <h3>{{ $submission->listing->title }}</h3>
                <p><strong>Final Sale Amount:</strong> <span class="amount">{{ number_format($auctionHistory->bid_amount, 2) }} SAR</span></p>
                <p><strong>Validation Date:</strong> {{ $auctionHistory->validated_at->format('M d, Y H:i') }}</p>
                <p><strong>Original Bid Date:</strong> {{ $auctionHistory->bid_date->format('M d, Y H:i') }}</p>
            </div>

            <div class="contact-info">
                <h3>📞 Contact Information</h3>
                <div class="contact-flex">
                    <div class="contact-card">
                        <h4>🛍️ Seller Details:</h4>
                        <p><strong>Name:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
                        <p><strong>Email:</strong> <a href="mailto:{{ $seller->email }}" style="color: #f03d24;">{{ $seller->email }}</a></p>
                        @if($seller->phone)
                        <p><strong>Phone:</strong> <a href="tel:{{ $seller->phone }}" style="color: #f03d24;">{{ $seller->phone }}</a></p>
                        @endif
                    </div>
                    <div class="contact-card">
                        <h4>🛒 Buyer Details:</h4>
                        <p><strong>Name:</strong> {{ $buyer->first_name }} {{ $buyer->last_name }}</p>
                        <p><strong>Email:</strong> <a href="mailto:{{ $buyer->email }}" style="color: #f03d24;">{{ $buyer->email }}</a></p>
                        @if($buyer->phone)
                        <p><strong>Phone:</strong> <a href="tel:{{ $buyer->phone }}" style="color: #f03d24;">{{ $buyer->phone }}</a></p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="important">
                <h4>📋 Next Steps:</h4>
                <ol>
                    <li><strong>Contact each other</strong> to arrange payment and delivery details</li>
                    <li><strong>Agree on meeting location and time</strong> for the transaction</li>
                    <li><strong>Verify the item condition</strong> before finalizing the payment</li>
                    <li><strong>Complete the transaction safely</strong> - consider meeting in public places</li>
                </ol>
            </div>

            <div class="safety">
                <h4>🔒 Safety Tips:</h4>
                <ul>
                    <li>Meet in safe, public locations</li>
                    <li>Bring a friend if possible</li>
                    <li>Verify payment before handing over items</li>
                    <li>Trust your instincts</li>
                </ul>
            </div>
        </div>

        <div class="content arabic">
            <h2>تهانينا! تم تأكيد البيع.</h2>

            <div class="section">
                <h3>{{ $submission->listing->title }}</h3>
                <p><strong>مبلغ البيع النهائي:</strong> <span class="amount">{{ number_format($auctionHistory->bid_amount, 2) }} ريال</span></p>
                <p><strong>تاريخ التأكيد:</strong> {{ $auctionHistory->validated_at->format('M d, Y H:i') }}</p>
                <p><strong>تاريخ العرض الأصلي:</strong> {{ $auctionHistory->bid_date->format('M d, Y H:i') }}</p>
            </div>

            <div class="contact-info">
                <h3>📞 معلومات الاتصال</h3>
                <div class="contact-flex">
                    <div class="contact-card">
                        <h4>🛍️ تفاصيل البائع:</h4>
                        <p><strong>الاسم:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
                        <p><strong>البريد الإلكتروني:</strong> <a href="mailto:{{ $seller->email }}" style="color: #f03d24;">{{ $seller->email }}</a></p>
                        @if($seller->phone)
                        <p><strong>الهاتف:</strong> <a href="tel:{{ $seller->phone }}" style="color: #f03d24;">{{ $seller->phone }}</a></p>
                        @endif
                    </div>
                    <div class="contact-card">
                        <h4>🛒 تفاصيل المشتري:</h4>
                        <p><strong>الاسم:</strong> {{ $buyer->first_name }} {{ $buyer->last_name }}</p>
                        <p><strong>البريد الإلكتروني:</strong> <a href="mailto:{{ $buyer->email }}" style="color: #f03d24;">{{ $buyer->email }}</a></p>
                        @if($buyer->phone)
                        <p><strong>الهاتف:</strong> <a href="tel:{{ $buyer->phone }}" style="color: #f03d24;">{{ $buyer->phone }}</a></p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="important">
                <h4>📋 الخطوات التالية:</h4>
                <ol>
                    <li><strong>التواصل مع بعضكما البعض</strong> لترتيب تفاصيل الدفع والتسليم</li>
                    <li><strong>الاتفاق على مكان ووقت اللقاء</strong> لإتمام المعاملة</li>
                    <li><strong>التحقق من حالة السلعة</strong> قبل إنهاء عملية الدفع</li>
                    <li><strong>إتمام المعاملة بأمان</strong> - فكر في اللقاء في أماكن عامة</li>
                </ol>
            </div>

            <div class="safety">
                <h4>🔒 نصائح الأمان:</h4>
                <ul>
                    <li>الاجتماع في أماكن آمنة وعامة</li>
                    <li>إحضار صديق إذا أمكن</li>
                    <li>التحقق من الدفع قبل تسليم السلع</li>
                    <li>الثقة في غرائزك</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p style="margin: 0; font-size: 16px;"><strong>Transaction ID:</strong> {{ $auctionHistory->id }}</p>
            <p style="margin: 10px 0 0 0; opacity: 0.8;">DabApp</p>
        </div>
    </div>
</body>
</html>
