<!DOCTYPE html>
<html>
<head>
    <title>Subscription Summary Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1, h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f4f4f4; }
    </style>
</head>
<body>
    <h1>Subscription Summary Report</h1>
    <p><strong>Filters:</strong></p>
    <ul>
        <li>Start Date: {{ $filters['start_date'] }}</li>
        <li>End Date: {{ $filters['end_date'] }}</li>
        <li>Branch: {{ $filters['branch'] }}</li>
        <li>Generated at: {{ $generatedAt }}</li>
    </ul>

    <h2>Summary</h2>

    <table>
        <tr>
            <th>Total Subscriptions</th>
            <td>{{ $reportData['totalSubscriptions'] }}</td>
        </tr>
        <tr>
            <th>Total Amount</th>
            <td>{{ number_format($reportData['totalAmount'], 2) }}</td>
        </tr>
    </table>

    <h3>Branch Stats</h3>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th>Total Subscription</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportData['branchStats'] as $branch => $stats)
                <tr>
                    <td>{{ $branch }}</td>
                    <td>{{ $stats['totalSubscriptions'] }}</td>
                    <td>{{ number_format($stats['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ✅ Only render Insurance Stats if data exists --}}
    @if (!empty($reportData['insuranceStats']))
        <h3>Insurance Stats</h3>
        <table>
            <thead>
                <tr>
                    <th>Insurance</th>
                    <th>Total Subscription</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reportData['insuranceStats'] as $insurance => $stats)
                    <tr>
                        <td>{{ $insurance }}</td>
                        <td>{{ $stats['totalSubscriptions'] }}</td>
                        <td>{{ number_format($stats['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
