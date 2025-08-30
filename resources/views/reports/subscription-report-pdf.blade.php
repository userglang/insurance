{{-- resources/views/reports/subscription-report-pdf.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Subscription Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .report-title {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }

        .report-info {
            font-size: 10px;
            color: #888;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin: 25px 0 15px 0;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            padding: 8px;
            font-weight: bold;
            font-size: 10px;
            text-align: left;
        }

        td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            font-size: 9px;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .status-active { color: #22c55e; font-weight: bold; }
        .status-expired { color: #ef4444; font-weight: bold; }
        .status-future { color: #f59e0b; font-weight: bold; }
        .status-archived { color: #9ca3af; font-weight: bold; }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <div class="company-name">Oro Integrated Cooperative</div>
    <div class="report-title">Insurance Report</div>
    <div class="report-info">
        Report Period: {{ \Carbon\Carbon::parse($filters['start_date'])->format('F d, Y') }} - {{ \Carbon\Carbon::parse($filters['end_date'])->format('F d, Y') }}<br>
        Branch: {{ $filters['branch'] }}<br>
        Generated: {{ $generatedAt }}
    </div>
</div>

{{-- Summary Section --}}
<div class="section-title">Report Summary</div>
<table>
    <thead>
        <tr>
            <th>Metric</th>
            <th>Value</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Total Members</td>
            <td>{{ number_format($reportData['totalMembers']) }}</td>
        </tr>
        <tr>
            <td>Total Subscriptions</td>
            <td>{{ number_format($reportData['totalSubscriptions']) }}</td>
        </tr>
        <tr>
            <td>Total Amount</td>
            <td><b>P</b> {{ number_format($reportData['totalAmount'], 2) }}</td>
        </tr>
        <tr>
            <td>Active</td>
            <td>{{ number_format($reportData['statusCounts']['active']) }}</td>
        </tr>
        <tr>
            <td>Future</td>
            <td>{{ number_format($reportData['statusCounts']['future']) }}</td>
        </tr>
        <tr>
            <td>Expired</td>
            <td>{{ number_format($reportData['statusCounts']['expired']) }}</td>
        </tr>
        <tr>
            <td>Archived</td>
            <td>{{ number_format($reportData['statusCounts']['archived']) }}</td>
        </tr>
    </tbody>
</table>

{{-- Branch Statistics --}}
@if(count($reportData['branchStats']) > 0)
    <div class="section-title">Branch Statistics</div>
    <table>
        <thead>
        <tr>
            <th>Branch</th>
            <th>Total Subscriptions</th>
            <th>Total Members</th>
            <th>Active</th>
            <th>Future</th>
            <th>Expired</th>
            <th>Archived</th>
            <th>Total Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach($reportData['branchStats'] as $branch => $stats)
            <tr>
                <td>{{ $branch }}</td>
                <td>{{ number_format($stats['totalSubscriptions']) }}</td>
                <td>{{ number_format($stats['totalMembers']) }}</td>
                <td>{{ number_format($stats['active']) }}</td>
                <td>{{ number_format($stats['future'] ?? 0) }}</td>
                <td>{{ number_format($stats['expired']) }}</td>
                <td>{{ number_format($stats['archived']) }}</td>
                <td><b>P</b> {{ number_format($stats['amount'], 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

{{-- Insurance Statistics --}}
@if(count($reportData['insuranceStats']) > 0)
    <div class="section-title">Insurance Statistics</div>
    <table>
        <thead>
        <tr>
            <th>Insurance Type</th>
            <th>Total Subscriptions</th>
            <th>Total Members</th>
            <th>Active</th>
            <th>Future</th>
            <th>Expired</th>
            <th>Archived</th>
            <th>Total Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach($reportData['insuranceStats'] as $insurance => $stats)
            <tr>
                <td>{{ $insurance }}</td>
                <td>{{ number_format($stats['totalSubscriptions']) }}</td>
                <td>{{ number_format($stats['totalMembers']) }}</td>
                <td>{{ number_format($stats['active']) }}</td>
                <td>{{ number_format($stats['future'] ?? 0) }}</td>
                <td>{{ number_format($stats['expired']) }}</td>
                <td>{{ number_format($stats['archived']) }}</td>
                <td><b>P</b> {{ number_format($stats['amount'], 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

{{-- Detailed Subscription List --}}
<div class="page-break"></div>
<div class="section-title">Detailed Subscription List</div>
<table>
    <thead>
    <tr>
        <th>CID</th>
        <th>Member Name</th>
        <th>Branch</th>
        <th>Insurance</th>
        <th>Amount</th>
        <th>Payment Date</th>
        <th>Activated</th>
        <th>Expires</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach($subscriptions as $subscription)
        @php
            $status = $subscription->member->is_active ? $subscription->status : 'archived';
        @endphp
        <tr>
            <td>{{ $subscription->member->cid }}</td>
            <td>{{ $subscription->member->full_name }}</td>
            <td>{{ $subscription->member->branch?->branch_name ?? 'N/A' }}</td>
            <td>{{ $subscription->insurance?->insurance_name ?? 'N/A' }}</td>
            <td><b>P</b> {{ number_format($subscription->amount, 2) }}</td>
            <td>{{ $subscription->payment_date?->format('M j, Y') }}</td>
            <td>{{ $subscription->activated_at?->format('M j, Y') }}</td>
            <td>{{ $subscription->expires_at?->format('M j, Y') }}</td>
            <td class="status-{{ $status }}">
                {{ ucfirst($status) }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- Footer --}}
<div class="footer">
    <p>This report was automatically generated by the system on {{ $generatedAt }}</p>
    <p>Total Records: {{ number_format($subscriptions->count()) }}</p>
</div>

</body>
</html>
