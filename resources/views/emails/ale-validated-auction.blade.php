<!-- resources/views/emails/sale-validated-auction.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sale Validated - Contact Information</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 8px; }
        .header { background-color: #28a745; color: white; padding: 15px; border-radius: 5px; text-align: center; }
        .content { padding: 20px 0; }
        .amount { font-size: 24px; font-weight: bold; color: #28a745; }
        .arabic { direction: rtl; text-align: right; }
        .english { direction: ltr; text-align: left; }
        .contact-info { background-color: #e7f3ff; border: 2px solid #007bff; padding: 20px; border-radius: 5px; margin: 15px 0; }
        .important { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .contact-card { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Sale Validated / تم تأكيد البيع</h1>
        </div>

        <div class="content english">
            <h2>Congratulations! The sale has been validated.</h2>

            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h3>{{ $submission->listing->title }}</h3>
                <p><strong>Final Sale Amount:</strong> <span class="amount">${{ number_format($auctionHistory->bid_amount, 2) }}</span></p>
                <p><strong>Validation Date:</strong> {{ $auctionHistory->validated_at->format('M d, Y H:i') }}</p>
                <p><strong>Original Bid Date:</strong> {{ $auctionHistory->bid_date->format('M d, Y H:i') }}</p>
            </div>

            <div class="contact-info">
                <h3>📞 Contact Information</h3>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
                    <div class="contact-card" style="width: 48%;">
                        <h4>🛍️ Seller Details:</h4>
                        <p><strong>Name:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
                        <p><strong>Email:</strong> <a href="mailto:{{ $seller->email }}">{{ $seller->email }}</a></p>
                        @if($seller->phone)
                        <p><strong>Phone:</strong> <a href="tel:{{ $seller->phone }}">{{ $seller->phone }}</a></p>
                        @endif
                        @if($seller->address)
                        <p><strong>Address:</strong> {{ $seller->address }}</p>
                        @endif
                    </div>
                    <div class="contact-card" style="width: 48%;">
                        <h4>🛒 Buyer Details:</h4>
                        <p><strong>Name:</strong> {{ $buyer->first_name }} {{ $buyer->last_name }}</p>
                        <p><strong>Email:</strong> <a href="mailto:{{ $buyer->email }}">{{ $buyer->email }}</a></p>
                        @if($buyer->phone)
                        <p><strong>Phone:</strong> <a href="tel:{{ $buyer->phone }}">{{ $buyer->phone }}</a></p>
                        @endif
                        @if($buyer->address)
                        <p><strong>Address:</strong> {{ $buyer->address }}</p>
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
                    <li><strong>Confirm receipt</strong> once the transaction is completed</li>
                </ol>
            </div>

            <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h4>🔒 Safety Tips:</h4>
                <ul>
                    <li>Meet in safe, public locations</li>
                    <li>Bring a friend if possible</li>
                    <li>Verify payment before handing over items</li>
                    <li>Trust your instincts - if something feels wrong, cancel the meeting</li>
                </ul>
            </div>
        </div>

        <hr style="margin: 30px 0; border: 1px solid #ddd;">

        <div class="content arabic">
            <h2>تهانينا! تم تأكيد البيع.</h2>

            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h3>{{ $submission->listing->title }}</h3>
                <p><strong>مبلغ البيع النهائي:</strong> <span class="amount">${{ number_format($auctionHistory->bid_amount, 2) }}</span></p>
                <p><strong>تاريخ التأكيد:</strong> {{ $auctionHistory->validated_at->format('M d, Y H:i') }}</p>
                <p><strong>تاريخ العرض الأصلي:</strong> {{ $auctionHistory->bid_date->format('M d, Y H:i') }}</p>
            </div>

            <div class="contact-info">
                <h3>📞 معلومات الاتصال</h3>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
                    <div class="contact-card" style="width: 48%;">
                        <h4>🛍️ تفاصيل البائع:</h4>
                        <p><strong>الاسم:</strong> {{ $seller->first_name }} {{ $seller->last_name }}</p>
                        <p><strong>البريد الإلكتروني:</strong> <a href="mailto:{{ $seller->email }}">{{ $seller->email }}</a></p>
                        @if($seller->phone)
                        <p><strong>الهاتف:</strong> <a href="tel:{{ $seller->phone }}">{{ $seller->phone }}</a></p>
                        @endif
                        @if($seller->address)
                        <p><strong>العنوان:</strong> {{ $seller->address }}</p>
                        @endif
                    </div>
                    <div class="contact-card" style="width: 48%;">
                        <h4>🛒 تفاصيل المشتري:</h4>
                        <p><strong>الاسم:</strong> {{ $buyer->first_name }} {{ $buyer->last_name }}</p>
                        <p><strong>البريد الإلكتروني:</strong> <a href="mailto:{{ $buyer->email }}">{{ $buyer->email }}</a></p>
                        @if($buyer->phone)
                        <p><strong>الهاتف:</strong> <a href="tel:{{ $buyer->phone }}">{{ $buyer->phone }}</a></p>
                        @endif
                        @if($buyer->address)
                        <p><strong>العنوان:</strong> {{ $buyer->address }}</p>
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
                    <li><strong>تأكيد الاستلام</strong> بمجرد إتمام المعاملة</li>
                </ol>
            </div>

            <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h4>🔒 نصائح الأمان:</h4>
                <ul>
                    <li>الاجتماع في أماكن آمنة وعامة</li>
                    <li>إحضار صديق إذا أمكن</li>
                    <li>التحقق من الدفع قبل تسليم السلع</li>
                    <li>الثقة في غرائزك - إذا شعرت بأن شيئاً ما خطأ، ألغ الاجتماع</li>
                </ul>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 5px;">
            <p style="margin: 0; color: #6c757d; font-size: 14px;">
                <strong>Transaction ID:</strong> {{ $auctionHistory->id }} |
                <strong>Listing ID:</strong> {{ $submission->listing->id }}
            </p>
            <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 12px;">
                This email was sent automatically by the SOOM platform.
            </p>
        </div>
    </div>
</body>
</html>
