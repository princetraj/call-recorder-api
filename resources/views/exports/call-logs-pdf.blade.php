<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Call Logs Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
            font-size: 20px;
            margin-bottom: 10px;
        }
        .export-info {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background-color: #4A5568;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-incoming {
            background-color: #10b981;
            color: white;
        }
        .badge-outgoing {
            background-color: #3b82f6;
            color: white;
        }
        .badge-missed {
            background-color: #f59e0b;
            color: white;
        }
        .badge-rejected {
            background-color: #ef4444;
            color: white;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #999;
            padding: 10px;
        }
    </style>
</head>
<body>
    <h1>Call Logs Export</h1>
    <div class="export-info">
        Generated on: {{ $exportDate }}
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Caller</th>
                <th>Number</th>
                <th>Type</th>
                <th>Duration</th>
                <th>Date & Time</th>
                <th>Agent</th>
                <th>Branch</th>
                <th>SIM</th>
            </tr>
        </thead>
        <tbody>
            @foreach($callLogs as $log)
            <tr>
                <td>{{ $log->id }}</td>
                <td>{{ $log->caller_name ?? 'Unknown' }}</td>
                <td>{{ $log->caller_number }}</td>
                <td>
                    <span class="badge badge-{{ $log->call_type }}">
                        {{ ucfirst($log->call_type) }}
                    </span>
                </td>
                <td>{{ floor($log->call_duration / 60) }}:{{ str_pad($log->call_duration % 60, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $log->call_timestamp->format('Y-m-d H:i') }}</td>
                <td>{{ $log->user ? $log->user->name : 'N/A' }}</td>
                <td>{{ $log->user && $log->user->branch ? $log->user->branch->name : 'N/A' }}</td>
                <td>{{ $log->sim_name ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if(count($callLogs) >= 500)
    <div style="margin-top: 20px; padding: 10px; background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px;">
        <strong>Note:</strong> This PDF export is limited to 500 records. For complete data, please use Excel export.
    </div>
    @endif

    <div class="footer">
        Call Logs Management System - Page <span class="pagenum"></span>
    </div>
</body>
</html>
