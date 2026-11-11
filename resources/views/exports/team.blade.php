@php
    $orgColor = optional($organisation ?? null)->primary_color ?? '#0d9488';
@endphp
<!DOCTYPE html>
<html>
<head>
    <title>Worker Productivity Report</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #333; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 40px; }
        .header img { max-height: 100px; margin-bottom: 15px; }
        .header h1 { color: {{ $orgColor }}; margin: 15px 0 10px 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
        .header p { color: #666; font-size: 14px; margin-top: 0; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 30px; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 16px 12px; text-align: left; }
        th { background-color: {{ $orgColor }}; color: #fff; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        th:first-child { border-top-left-radius: 6px; }
        th:last-child { border-top-right-radius: 6px; }
        td { border-bottom: 1px solid #edf2f7; color: #4a5568; }
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .pct { font-weight: bold; color: {{ $orgColor }}; }
    </style>
</head>
<body>
    <div class="header">
        @if(isset($letterhead_base64))
            <img src="{{ $letterhead_base64 }}" alt="Letterhead" style="width: 100%; max-height: 150px; object-fit: cover;">
        @elseif(isset($logo_base64))
            <img src="{{ $logo_base64 }}" alt="Logo">
        @endif
        <h1>{{ optional($organisation ?? null)->name ?? 'Organisation' }} Productivity Report</h1>
        <p>Generated on: {{ date('F j, Y') }} | Period: {{ $periodTitle ?? 'This Week' }}</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Worker</th>
                <th>Department</th>
                <th>Total Hours</th>
                <th>Direct Work %</th>
                <th>Execution Rate</th>
                <th>Total Planned</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report as $worker)
            <tr>
                <td><strong>{{ $worker['user'] }}</strong></td>
                <td>{{ $worker['department'] }}</td>
                <td>{{ $worker['total_hours'] }} hrs</td>
                <td class="pct">{{ $worker['direct_pct'] }}%</td>
                <td>{{ $worker['execution_rate'] }}%</td>
                <td>{{ $worker['total_planned'] }} task(s)</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
