<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $listing->title }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            /* Better for UTF-8/Arabic support */
            color: #333;
            line-height: 1.5;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #F03D24;
            /* DabApp Red */
            padding-bottom: 10px;
        }

        .header h1 {
            color: #F03D24;
            margin: 0;
        }

        .title-section {
            margin-bottom: 20px;
        }

        .price {
            font-size: 24px;
            font-weight: bold;
            color: #F03D24;
        }

        .images-grid {
            width: 100%;
            margin-bottom: 30px;
        }

        .images-grid table {
            width: 100%;
            border-collapse: collapse;
        }

        .images-grid td {
            width: 50%;
            padding: 5px;
            text-align: center;
        }

        .img-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background-color: #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .img-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .details-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            width: 30%;
        }

        .description {
            margin-bottom: 30px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 50px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .rtl {
            direction: rtl;
            text-align: right;
        }
    </style>
</head>

<body>

    <div class="header">
        <img src="{{ public_path('LogoDabApp.png') }}" alt="DabApp Logo" style="height: 50px;">
    </div>

    <div class="title-section">
        <h2>{{ $listing->title }}</h2>
        <div class="price">
            {{ number_format($listing->price, 0) }} {{ $listing->currency ?? 'AED' }}
        </div>
        <p style="color: #666;">
            📍 {{ $listing->city->name ?? 'Unknown City' }}, {{ $listing->country->name ?? 'Unknown Country' }}
        </p>
    </div>

    @if($images->count() > 0)
        <div class="images-grid">
            <table>
                <tr>
                    @foreach($images as $index => $img)
                        <td>
                            <div class="img-container">
                                <img src="{{ $img->image_url }}" alt="Listing Image">
                            </div>
                        </td>
                        @if(($index + 1) % 2 == 0 && !$loop->last)
                            </tr>
                            <tr>
                        @endif
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    <div class="details-section">
        <h3>Details</h3>
        <table class="details-table">
            <tr>
                <th>Brand</th>
                <td>
                    @if($listing->motorcycle) {{ $listing->motorcycle->brand->name ?? '-' }}
                    @elseif($listing->sparePart) {{ $listing->sparePart->bikePartBrand->name ?? '-' }}
                    @else - @endif
                </td>
                <th>Model</th>
                <td>
                    @if($listing->motorcycle) {{ $listing->motorcycle->model->name ?? '-' }}
                    @else - @endif
                </td>
            </tr>

            @if($listing->motorcycle)
                <tr>
                    <th>Year</th>
                    <td>{{ $listing->motorcycle->year->year ?? '-' }}</td>
                    <th>Engine Capacity</th>
                    <td>{{ $listing->motorcycle->engine ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Kilometer</th>
                    <td>{{ $listing->motorcycle->mileage ? number_format($listing->motorcycle->mileage) : '-' }}</td>
                    <th>Transmission</th>
                    <td>{{ $listing->motorcycle->transmission ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Vehicle Body Condition</th>
                    <td>{{ $listing->motorcycle->body_condition ?? '-' }}</td>
                    <th>Condition</th>
                    <td>{{ $listing->motorcycle->general_condition ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Vehicle Care</th>
                    <td>
                        {{ $listing->motorcycle->vehicle_care ?? '-' }}
                        @if($listing->motorcycle->vehicle_care === 'Other' && !empty($listing->motorcycle->vehicle_care_other))
                            ({{ $listing->motorcycle->vehicle_care_other }})
                        @endif
                    </td>
                    <th>Is Modified</th>
                    <td>{{ $listing->motorcycle->modified ? 'Yes' : 'No' }}</td>
                </tr>
                <tr>
                    <th>Has Warranty ?</th>
                    <td>{{ $listing->motorcycle->insurance ? 'Yes' : 'No' }}</td>
                    <!-- Mapping Insurance to Warranty/Insurance as discussed -->
                    <th>The Seller Is a</th>
                    <td>{{ ucfirst($listing->seller_type) }}</td>
                </tr>
            @endif

            @if($listing->sparePart)
                <tr>
                    <th>Condition</th>
                    <td>{{ ucfirst($listing->sparePart->condition) }}</td>
                    <th>Category</th>
                    <td>{{ $listing->sparePart->bikePartCategory->name ?? '-' }}</td>
                </tr>
            @endif

            @if($listing->licensePlate)
                <tr>
                    <th>Format</th>
                    <td>{{ $listing->licensePlate->plateFormat->name ?? '-' }}</td>
                    <th>City</th>
                    <td>{{ $listing->licensePlate->city->name ?? '-' }}</td>
                </tr>
            @endif

            <tr>
                <th>Seller Verified</th>
                <td>{{ $listing->seller && $listing->seller->is_verified ? 'Yes' : 'No' }}</td>
                <th>Contact Method</th>
                <td>{{ $listing->contacting_channel ? ucfirst($listing->contacting_channel) : '-' }}</td>
            </tr>
            <tr>
                <th>Soom Enabled</th>
                <td>{{ $listing->auction_enabled ? 'Yes' : 'No' }}</td>
                <th>Listed On</th>
                <td>{{ $listing->created_at->format('d M Y') }}</td>
            </tr>

            @if($listing->auction_enabled)
                <tr>
                    <th>Minimum Bid</th>
                    <td>{{ number_format($listing->minimum_bid, 2) }} {{ $listing->currency ?? 'AED' }}</td>
                    <th>Current Highest Bid</th>
                    <td>{{ $currentBid ? number_format($currentBid, 2) : '-' }} {{ $listing->currency ?? 'AED' }}</td>
                </tr>
            @endif
        </table>
    </div>

    @if($listing->auction_enabled && $listing->submissions->count() > 0)
        <div class="submissions-section">
            <h3>Recent Submissions (SOOMs)</h3>
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($listing->submissions->take(5) as $submission)
                        <tr>
                            <td>{{ $submission->submission_date ? \Carbon\Carbon::parse($submission->submission_date)->format('d M Y H:i') : '-' }}
                            </td>
                            <td style="font-weight: bold;">{{ number_format($submission->amount, 2) }}
                                {{ $listing->currency ?? 'AED' }}
                            </td>
                            <td>{{ ucfirst($submission->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif


    <div class="description">
        <h3>Description</h3>
        <p>{!! nl2br(e($listing->description)) !!}</p>
    </div>

    <div class="footer">
        <p>Visit <strong><a href="https://dabapp.co"
                    style="color: #F03D24; text-decoration: none;">dabapp.co</a></strong></p>
    </div>

</body>

</html>