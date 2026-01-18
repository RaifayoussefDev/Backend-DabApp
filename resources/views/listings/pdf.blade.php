<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $listing->title }}</title>
    <style>
        @page {
            margin: 0px;
        }

        @font-face {
            font-family: 'Noto Sans Arabic';
            src: url({{ public_path('fonts/NotoSansArabic.ttf') }}) format("truetype");
            font-weight: normal; 
            font-style: normal; 
        }

        body {
            font-family: 'Noto Sans Arabic', 'DejaVu Sans', sans-serif;
            color: #1a2b4b;
            /* Dark Blue Text */
            margin: 0;
            padding: 30px;
        }

        /* Header */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .header-logo-cell {
            width: 1%;
            white-space: nowrap;
            padding-right: 20px;
            vertical-align: middle;
        }
        .header-line-cell {
            vertical-align: middle;
            width: 99%;
        }
        .header-line {
            border-bottom: 2px solid #1a2b4b;
            width: 100%;
            height: 1px; /* visual fix */
        }

        /* Image Grid */
        .image-grid {
            width: 100%;
            border-spacing: 15px;
            margin-bottom: 20px;
        }
        .image-cell {
            width: 50%;
            height: 240px;
            vertical-align: middle;
            text-align: center;
            border: 2px solid #F03D24; /* Match DabApp Red */
            overflow: hidden;
            padding: 0;
            background-color: white;
            border-radius: 0 40px 0 0; /* Top-Right only */
        }

        .image-cell img {
            width: 100%;
            height: 240px;
            object-fit: cover;
            /* Border radius on img needs to match container or overflow hidden handles it */
        }

        /* Price Tag */
        .price-tag {
            display: inline-block;
            background-color: #F03D24;
            color: white;
            font-size: 20px;
            font-weight: bold;
            padding: 8px 25px;
            /* Top-Right heavy rounding to match Image 2 */
            border-radius: 4px 25px 4px 4px;
            margin-bottom: 10px;
        }

        /* Title Section */
        h1 {
            font-size: 24px;
            color: #031b4e;
            margin: 5px 0;
        }

        .sub-info {
            font-size: 14px;
            color: #555;
            margin-bottom: 60px; /* Increased from 20px */
        }

        .sub-info span {
            font-weight: bold;
            color: #1a2b4b;
        }

        /* Buttons */
        .buttons-table {
            width: 100%;
            border-spacing: 20px 0;
            margin-bottom: 30px;
        }

        .btn {
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            color: #1a2b4b;
            font-size: 14px;
        }

        .btn-whatsapp {
            background-color: #dcf8c6;
            /* WhatsApp light green */
            color: #075e54;
            border: 1px solid #25D366;
        }

        .btn-call {
            background-color: #fadbd8;
            /* Light red */
            color: #c0392b;
            border: 1px solid #e74c3c;
        }

        /* Description */
        .section-title {
            font-size: 22px;
            color: #031b4e;
            font-weight: bold;
            margin-bottom: 10px;
            margin-top: 20px;
            display: flex;
            align-items: center;
        }

        /* Icon placeholder - using text/emoji since custom icons might fail without local path */
        .icon-moto {
            color: #F03D24;
            margin-right: 10px;
            font-size: 28px;
        }

        .description-text {
            font-size: 14px;
            line-height: 1.4;
            color: #333;
            margin-bottom: 30px;
        }

        /* Details Table */
        .details-title {
            color: #F03D24;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
        }

        .details-table td {
            padding: 10px;
            border: 1px solid #e1e4e8;
            font-size: 13px;
            vertical-align: middle;
        }

        .details-table .label {
            font-weight: bold;
            color: #031b4e;
            background-color: #f9fafb;
            width: 25%;
        }

        .details-table .value {
            color: #555;
            width: 25%;
        }

        /* Footer */
        .footer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #0d1b30;
            /* Dark Footer */
            color: white;
            text-align: center;
            padding: 20px 0;
        }

        .footer-logo {
            height: 30px;
            margin-bottom: 10px;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
                <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('LogoDabApp.png'))) }}" alt="DabApp Logo" style="height: 40px;">
            </td>
            <td class="header-line-cell">
                <div class="header-line"></div>
            </td>
        </tr>
    </table>

    <!-- Images -->
    @if($images->count() > 0)
    <table class="image-grid">
        <tr>
            <!-- Row 1 Left -->
            @if($images->count() >= 1)
            <td class="image-cell" style="vertical-align: top;">
                <div style="width: 100%; height: 240px; background-image: url('{{ $images[0]->image_url }}'); background-size: cover; background-position: center;"></div>
            </td>
            @endif
            
            <!-- Row 1 Right -->
            @if($images->count() >= 2)
            <td class="image-cell" style="vertical-align: top;">
                <div style="width: 100%; height: 240px; background-image: url('{{ $images[1]->image_url }}'); background-size: cover; background-position: center; border-radius: 0 40px 0 0;"></div>
            </td>
            @endif
        </tr>
        @if($images->count() >= 3)
        <tr>
            <!-- Row 2 Left -->
            <td class="image-cell" style="vertical-align: top;">
                 <div style="width: 100%; height: 240px; background-image: url('{{ $images[2]->image_url }}'); background-size: cover; background-position: center;"></div>
            </td>
            
            <!-- Row 2 Right -->
            @if($images->count() >= 4)
            <td class="image-cell" style="vertical-align: top;">
                 <div style="width: 100%; height: 240px; background-image: url('{{ $images[3]->image_url }}'); background-size: cover; background-position: center; border-radius: 0 40px 0 0;"></div>
            </td>
            @else
            <td></td>
            @endif
        </tr>
        @endif
    </table>
    @endif

    <!-- Price -->
    <div class="price-tag">
        {{ $displayPrice ? number_format($displayPrice, 0) : '0' }} {{ $currency }}
    </div>

    <!-- Title -->
    <h1>{{ $listing->title }}</h1>

    <div class="sub-info">
        <span>Phone:</span> {{ $listing->seller->phone ?? 'Unknown' }} <br>
        <span>Location:</span> {{ $listing->city->name ?? '' }}, {{ $listing->country->name ?? '' }}
    </div>

    <!-- Buttons -->
    <!-- Buttons -->
    <table class="buttons-table">
        <tr>
            @php
                $channel = strtolower($listing->contacting_channel ?? '');
                $showPhone = str_contains($channel, 'phone');
                $showWhatsapp = str_contains($channel, 'whatsapp');
                
                // If neither is present (e.g. legacy data), default to both or handle gracefully.
                // Assuming defaults to showing if null, but explicit check requested.
                if (!$showPhone && !$showWhatsapp) {
                     // Fallback if empty: show both or hide? User implies data exists.
                     // Let's assume broad match or default to phone if channel is empty but phone exists?
                     // Current code showed both. I will default to showing both if empty just in case.
                     $showPhone = true; 
                     $showWhatsapp = true;
                }
            @endphp

            @if($showWhatsapp && $showPhone)
                <td class="btn btn-whatsapp" width="48%">
                    <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('img/pdf_icons/WHATS APP.svg'))) }}" style="height: 16px; margin-right: 5px; vertical-align: middle;"> {{ $listing->seller->phone ?? 'Unavailable' }}
                </td>
                <td width="4%"></td> <!-- Spacing center -->
                <td class="btn btn-call" width="48%">
                    <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('img/pdf_icons/CALL.svg'))) }}" style="height: 16px; margin-right: 5px; vertical-align: middle;"> {{ $listing->seller->phone ?? 'Unavailable' }}
                </td>
            @elseif($showWhatsapp)
                <td class="btn btn-whatsapp" width="100%">
                    <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('img/pdf_icons/WHATS APP.svg'))) }}" style="height: 16px; margin-right: 5px; vertical-align: middle;"> {{ $listing->seller->phone ?? 'Unavailable' }}
                </td>
            @elseif($showPhone)
                <td class="btn btn-call" width="100%">
                    <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('img/pdf_icons/CALL.svg'))) }}" style="height: 16px; margin-right: 5px; vertical-align: middle;"> {{ $listing->seller->phone ?? 'Unavailable' }}
                </td>
            @endif
        </tr>
    </table>

    <div class="header-line" style="margin-top: 100px; margin-bottom: 20px;"></div>

    <div class="page-break"></div>

    <!-- Description -->
    <div class="section-title">
        <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('img/pdf_icons/BIKE.svg'))) }}" style="height: 24px; margin-right: 10px; vertical-align: middle;"> Description
    </div>
    <div class="description-text">
        {!! nl2br(e($listing->description)) !!}
    </div>

    <!-- Details -->
    <div class="details-title">Details</div>

    <table class="details-table">
        @if($listing->motorcycle)
            <tr>
                <td class="label">Brand</td>
                <td class="value">{{ $listing->motorcycle->brand->name ?? '-' }}</td>
                <td class="label">Model</td>
                <td class="value">{{ $listing->motorcycle->model->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Year</td>
                <td class="value">{{ $listing->motorcycle->year->year ?? '-' }}</td>
                <td class="label">Engine Capacity</td>
                <td class="value">{{ $listing->motorcycle->engine ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Kilometer</td>
                <td class="value">{{ $listing->motorcycle->mileage ? number_format($listing->motorcycle->mileage) : '-' }}
                </td>
                <td class="label">Transmission</td>
                <td class="value">{{ $listing->motorcycle->transmission ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Vehicle Body Condition</td>
                <td class="value">{{ $listing->motorcycle->body_condition ?? '-' }}</td>
                <td class="label">Condition</td>
                <td class="value">{{ $listing->motorcycle->general_condition ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Vehicle Care</td>
                <td class="value">
                    <div class="subtitle">
                        {{ number_format($listing->highest_bid, 0, ',', ' ') }} {{ $currency }} 
                    </div>@if($listing->motorcycle->vehicle_care === 'Other' && !empty($listing->motorcycle->vehicle_care_other))
                        ({{ $listing->motorcycle->vehicle_care_other }})
                    @endif
                </td>
                <td class="label">Is Modified</td>
                <td class="value">{{ $listing->motorcycle->modified ? 'Yes' : 'No' }}</td>
            </tr>
            <tr>
                <td class="label">Has Warranty ?</td>
                <td class="value">{{ $listing->motorcycle->insurance ? 'Yes' : 'No' }}</td>
                <td class="label">The Seller Is a</td>
                <td class="value">{{ ucfirst($listing->seller_type) }}</td>
            </tr>

        @elseif($listing->sparePart)
            <tr>
                <td class="label">Condition</td>
                <td class="value">{{ ucfirst($listing->sparePart->condition) }}</td>
                <td class="label">Category</td>
                <td class="value">{{ $listing->sparePart->bikePartCategory->name ?? ($listing->sparePart->category ?? '-') }}</td>
            </tr>
            <tr>
                <td class="label">Brand</td>
                <td class="value">
                    @php
                        $brand = $listing->sparePart->bikePartBrand->name ?? ($listing->sparePart->brand ?? '-');
                        if (strtolower($brand) === 'other' || strtolower($brand) === 'others') {
                            echo $listing->sparePart->brand_other ?? $brand;
                        } else {
                            echo $brand;
                        }
                    @endphp
                </td>
                <td class="label">Category</td>
                <td class="value">{{ ucfirst($listing->sparePart->category) }}</td>
            </tr>
        @elseif($listing->licensePlate)
            <tr>
                <td class="label">Format</td>
                <td class="value">{{ $listing->licensePlate->plateFormat->name ?? '-' }}</td>
                <td class="label">City</td>
                <td class="value">{{ $listing->licensePlate->city->name ?? '-' }}</td>
            </tr>
        @endif

        <tr>
            <td class="label">Contact Method</td>
            <td class="value">
                {{ $listing->contacting_channel ? ucwords(str_replace(',', ', ', $listing->contacting_channel)) : '-' }}
            </td>
            <td class="label">Listed On</td>
            <td class="value">{{ $listing->created_at->format('d M Y') }}</td>
        </tr>

        {{-- ✅ Compatible Motorcycles (Category 2 - Spare Parts) --}}
        @if($listing->category_id == 2 && $listing->sparePart && $listing->sparePart->motorcycles->count() > 0)
        <tr>
            <td class="label" colspan="4" style="text-align:center; background-color: #f0f0f0; color: #031b4e; font-weight:bold;">Compatible Motorcycles</td>
        </tr>
        @foreach($listing->sparePart->motorcycles as $moto)
        <tr>
            <td class="label">Brand</td>
            <td class="value">{{ $moto->brand->name ?? 'N/A' }}</td>
            <td class="label">Model / Year</td>
            <td class="value">
                {{ $moto->model->name ?? 'All' }} 
                @if($moto->year) ({{ $moto->year->year }}) @endif
            </td>
        </tr>
        @endforeach
        @endif
    </table>

    <!-- Footer -->
    <div class="footer">
    <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('img/pdf_icons/logo-footer.svg'))) }}" class="footer-logo">
        <br>
        <span style="font-size: 10px; color: #aaa;">2026 DabApp Technologies - All rights reserved</span>
    </div>

</body>

</html>