<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #2c3e50;
            background: #fff;
        }

        .header {
            background: linear-gradient(135deg, #1F2D3D 0%, #2c3e50 100%);
            color: #fff;
            padding: 16px 20px;
            margin-bottom: 16px;
        }

        .header h1 {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .header .meta {
            font-size: 9px;
            margin-top: 4px;
            opacity: 0.75;
        }

        .stats-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 20px 16px;
        }

        .stat-card {
            background: #EBF5FB;
            border-left: 3px solid #3498DB;
            padding: 8px 12px;
            border-radius: 4px;
            min-width: 120px;
        }

        .stat-card .stat-label {
            font-size: 8px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .stat-card .stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #1F2D3D;
            margin-top: 2px;
        }

        .table-wrapper {
            margin: 0 20px 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: #3498DB;
            color: #fff;
        }

        thead th {
            padding: 7px 8px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) {
            background: #f4f9fd;
        }

        tbody tr:nth-child(odd) {
            background: #fff;
        }

        tbody tr:hover {
            background: #d6eaf8;
        }

        tbody td {
            padding: 5px 8px;
            font-size: 9px;
            border-bottom: 1px solid #e8f4fb;
            vertical-align: middle;
        }

        .footer {
            text-align: center;
            font-size: 8px;
            color: #95a5a6;
            padding: 10px 20px;
            border-top: 1px solid #ecf0f1;
            margin-top: 10px;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }

        .badge-active, .badge-published, .badge-completed {
            background: #d5f5e3; color: #1e8449;
        }

        .badge-inactive, .badge-rejected, .badge-failed {
            background: #fadbd8; color: #a93226;
        }

        .badge-pending {
            background: #fef9e7; color: #b7950b;
        }

        .badge-draft {
            background: #eaf0fb; color: #2e4057;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="meta">
            Generated on {{ now()->format('F j, Y — H:i') }}
        </div>
    </div>

    {{-- Stats Cards --}}
    @if (!empty($stats))
        <div class="stats-bar">
            @foreach ($stats as $label => $value)
                <div class="stat-card">
                    <div class="stat-label">{{ $label }}</div>
                    <div class="stat-value">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Table --}}
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>
                                @php
                                    $lower = strtolower((string) $cell);
                                    $badgeMap = [
                                        'active'    => 'badge-active',
                                        'inactive'  => 'badge-inactive',
                                        'published' => 'badge-published',
                                        'pending'   => 'badge-pending',
                                        'rejected'  => 'badge-rejected',
                                        'draft'     => 'badge-draft',
                                        'completed' => 'badge-completed',
                                        'failed'    => 'badge-failed',
                                    ];
                                @endphp
                                @if (array_key_exists($lower, $badgeMap))
                                    <span class="badge {{ $badgeMap[$lower] }}">{{ $cell }}</span>
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" style="text-align:center; padding: 20px; color: #95a5a6;">
                            No data found for the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Footer --}}
    <div class="footer">
        DabApp Admin &mdash; {{ $title }} &mdash; {{ now()->format('Y-m-d H:i:s') }}
    </div>

</body>
</html>
