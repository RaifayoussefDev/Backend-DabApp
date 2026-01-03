<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $listing->title }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif; /* Better for UTF-8/Arabic support */
            color: #333;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #F03D24; /* DabApp Red */
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
        .details-table th, .details-table td {
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
        <h1>DabApp</h1>
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
                        </tr><tr>
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
                <th>Category</th>
                <td>
                    @if($listing->category_id == 1) Motorcycle
                    @elseif($listing->category_id == 2) Spare Part
                    @elseif($listing->category_id == 3) License Plate
                    @else Other @endif
                </td>
            </tr>
            
            @if($listing->motorcycle)
                <tr>
                    <th>Brand</th>
                    <td>{{ $listing->motorcycle->brand->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Model</th>
                    <td>{{ $listing->motorcycle->model->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Year</th>
                    <td>{{ $listing->motorcycle->year->year ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Mileage</th>
                    <td>{{ $listing->motorcycle->mileage ? number_format($listing->motorcycle->mileage) . ' km' : '-' }}</td>
                </tr>
            @endif

            @if($listing->sparePart)
                <tr>
                    <th>Condition</th>
                    <td>{{ ucfirst($listing->sparePart->condition) }}</td>
                </tr>
            @endif

            @if($listing->licensePlate)
                <tr>
                    <th>Format</th>
                    <td>{{ $listing->licensePlate->plateFormat->name ?? '-' }}</td>
                </tr>
            @endif

            <tr>
                <th>Seller Type</th>
                <td>{{ ucfirst($listing->seller_type) }}</td>
            </tr>
            <tr>
                <th>Listed On</th>
                <td>{{ $listing->created_at->format('d M Y') }}</td>
            </tr>
        </table>
    </div>

    <div class="description">
        <h3>Description</h3>
        <p>{!! nl2br(e($listing->description)) !!}</p>
    </div>

    <div class="footer">
        <p>Visit <strong>dabapp.co</strong></p>
    </div>

</body>
</html>
