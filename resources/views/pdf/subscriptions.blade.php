<!DOCTYPE html>
<html>
<head>
    <title>Subscription Report</title>
    <style>
        @page {
            margin: 30px 25px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
        }

        h1, h2, h3 {
            color: darkgreen;
            margin-bottom: 5px;
        }

        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            font-size: 10px;
        }

        th {
            background-color: #f2f2f2;
            text-align: left;
        }

        tr {
            page-break-inside: avoid;
        }

        .status {
            font-weight: bold;
            text-transform: capitalize;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            color: #888;
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .page-break {
            page-break-after: always;
        }

        .signatures {
            margin-top: 40px;
            width: 100%;
            font-size: 11px;
        }

        .signatures td {
            padding: 10px;
            vertical-align: top;
        }

        .sign-line {
            margin-top: 30px;
            border-top: 1px solid #000;
            width: 80%;
        }

        .notice {
            margin-top: 30px;
            font-size: 11px;
            background-color: #fcf8e3;
            padding: 10px;
            border: 1px dashed #e0c97f;
        }
    </style>
</head>
<body>

<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="margin: 0; font-size: 14px;">WHERE FINANCIAL FREEDOM BEGINS</h2>
    <p style="margin: 4px 0; font-size: 10px">
        Main Office Address: Tiano Yacapin Streets, Barangay 11, <br>
        Cagayan de Oro City, 9000 Misamis Oriental, Philippines <br> </p>
    <p style="margin-top: 0; font-size: 8px">
        <strong style="font-size: 20px"> {{ strtoupper($member->branch->branch_name ?? 'Branch Name') }} </strong> <br>
        {{ $member->branch->address ?? 'Branch Address' }}
    </p>
</div>

<div class="section">
    <p>
        <strong>Name:</strong> {{ $member->full_name }} <br>
        <strong>Email:</strong> {{ $member->email ?? '' }} <br>
        <strong>Contact Number:</strong> {{ $member->contact_number ?? '' }} <br>
        <strong>Total Subscriptions:</strong> {{ $subscriptions->count() }}
    </p>
</div>

@if($subscriptions->count() > 0)
    <div class="section">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Insurance</th>
                <th>Account</th>
                <th>Amount</th>
                <th>Payment Date</th>
                <th>Activated</th>
                <th>Expires</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($subscriptions as $index => $sub)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $sub->insurance->insurance_name ?? 'N/A' }}</td>
                    <td>{{ $sub->productAccount->product_name ?? 'CASH' }}: {{$sub->productAccount->account_number ?? '0'}}</td>
                    <td>₱{{ number_format($sub->amount, 2) }}</td>
                    <td>{{ optional($sub->payment_date)->format('M d, Y') ?? 'N/A' }}</td>
                    <td>{{ optional($sub->activated_at)->format('M d, Y') ?? 'Pending' }}</td>
                    <td>{{ optional($sub->expires_at)->format('M d, Y') ?? 'N/A' }}</td>
                    <td class="status">{{ $sub->status }}</td>
                </tr>

                @if(($index + 1) % 25 === 0)
            </tbody>
        </table>
        <div class="page-break"></div>
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Insurance</th>
                <th>Method</th>
                <th>Amount</th>
                <th>Payment Date</th>
                <th>Activated</th>
                <th>Expires</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @endif
            @endforeach
            </tbody>
        </table>
    </div>
@else
    <p>No subscriptions found for this member.</p>
@endif

{{-- Signatures --}}
<table class="signatures">
    <tr>
        <td>
            <div class="sign-line"></div>
            <strong>Prepared by</strong>
        </td>
        <td>
            <div class="sign-line"></div>
            <strong>Checked by</strong>
        </td>
        <td>
            <div class="sign-line"></div>
            <strong>Approved by</strong>
        </td>
    </tr>
</table>

{{-- Important Notice --}}
<div class="notice">
    <strong>Important:</strong> This report contains confidential subscription data and is intended for authorized use only.
    Any unauthorized review, use, disclosure, or distribution is strictly prohibited. Please verify all data before taking further action.
</div>

{{-- Footer --}}
<div class="footer">
    {{ config('app.name') }} | insurance@orointegrated.coop | {{ now()->format('Y') }}
</div>

</body>
</html>
