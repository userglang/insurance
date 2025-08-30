<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Need Life Plan Application Form</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.4;
            color: #2c3e50;
            background: #f8f9fa;
            padding: 20px;
            font-size: 11px;
        }

        .document {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 20mm;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #0066cc;
            padding-bottom: 15px;
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
            gap: 15px;
        }

        .logo {
            width: 70px;
            height: 50px;
            background: linear-gradient(135deg, #0066cc, #0052a3);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 9px;
            text-align: center;
            line-height: 1.1;
            box-shadow: 0 2px 8px rgba(0,102,204,0.3);
        }

        .company-info {
            text-align: center;
        }

        .company-name {
            font-weight: bold;
            font-size: 14px;
            color: #0066cc;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }

        .company-motto {
            font-weight: 600;
            font-size: 9px;
            margin-bottom: 6px;
            color: #495057;
            font-style: italic;
        }

        .company-address {
            font-size: 8px;
            line-height: 1.3;
            margin-bottom: 10px;
            color: #6c757d;
        }

        .branch-info {
            font-weight: 600;
            font-size: 10px;
            margin-bottom: 3px;
            color: #495057;
            text-align: center;
        }

        .form-title {
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
            text-transform: uppercase;
            color: #0066cc;
            letter-spacing: 1px;
        }

        .form-dates {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            font-size: 9px;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 5px;
            border-left: 4px solid #0066cc;
        }

        .form-dates strong {
            color: #0066cc;
        }

        .section-title {
            font-weight: bold;
            font-size: 11px;
            margin: 15px 0 8px 0;
            text-transform: uppercase;
            color: #0066cc;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 3px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .info-table td {
            border: 1px solid #dee2e6;
            padding: 6px 8px;
            font-size: 9px;
            vertical-align: middle;
        }

        .label-cell {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            font-weight: 600;
            width: 15%;
            color: #495057;
        }

        .value-cell {
            background: white;
            font-weight: 600;
            width: 35%;
            color: #2c3e50;
            min-height: 20px;
        }

        .divider {
            border-top: 2px solid #0066cc;
            margin: 20px 0;
            position: relative;
        }

        .divider::after {
            content: '';
            position: absolute;
            top: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 2px;
            background: #28a745;
        }

        .authority-section {
            margin-top: 25px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #0066cc;
        }

        .authority-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
            text-transform: uppercase;
            color: #0066cc;
        }

        .authority-subtitle {
            font-size: 10px;
            margin-bottom: 15px;
            color: #6c757d;
            font-style: italic;
        }

        .payment-info {
            text-align: left;
            margin: 15px 0;
            font-size: 9px;
            background: white;
            padding: 15px;
            border-radius: 5px;
        }

        .payment-date {
            font-weight: bold;
            margin-bottom: 10px;
            color: #0066cc;
            font-size: 10px;
        }

        .payment-text {
            margin: 10px 0;
            line-height: 1.5;
            color: #495057;
        }

        .account-info {
            font-weight: bold;
            font-size: 11px;
            margin: 10px 0;
            color: #0066cc;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 3px;
            border-left: 3px solid #0066cc;
        }

        .amount-info {
            margin: 10px 0;
            font-weight: 600;
            color: #28a745;
            font-size: 10px;
        }

        .footer-section {
            margin-top: 30px;
            border-top: 2px solid #0066cc;
            padding-top: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
        }

        .footer-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            gap: 30px;
        }

        .signature-box {
            flex: 1;
            text-align: center;
            padding: 15px;
            background: white;
            border: 2px dashed #0066cc;
            border-radius: 5px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .signature-line {
            border-bottom: 2px solid #495057;
            margin: 20px 0 8px 0;
            height: 1px;
        }

        .signature-label {
            font-size: 9px;
            font-weight: 600;
            color: #495057;
            margin-top: 5px;
        }

        .applicant-name {
            color: #0066cc;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 5px;
        }

        .website-info {
            text-align: center;
            font-size: 8px;
            font-style: italic;
            margin-top: 15px;
            border-top: 1px solid #dee2e6;
            padding-top: 8px;
            color: #6c757d;
        }

        /* Print Styles */
        @media print {
            body {
                padding: 0;
                background: white;
            }

            .document {
                border: none;
                border-radius: 0;
                box-shadow: none;
                padding: 15mm;
            }

            .info-table {
                box-shadow: none;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .document {
                padding: 15px;
            }

            .form-dates {
                flex-direction: column;
                gap: 5px;
            }

            .footer-signatures {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
<div class="document">
    <!-- Header Section -->
    <div class="header">
        <div class="logo-container">
            <div class="logo">OIC<br>COOP</div>
            <div class="company-info">
                <div class="company-name">ORO INTEGRATED COOPERATIVE</div>
                <div class="company-motto">"WHERE FINANCIAL FREEDOM BEGINS"</div>
                <div class="company-address">
                    Main Office Address: Tiano Yacapin Streets, Barangay 11,<br>
                    Cagayan de Oro City, 9000 Misamis Oriental, Philippines
                </div>
            </div>
        </div>
        <div class="branch-info">OIC BUILDING 2ND FLR, AGUSAN NATIONAL HIGHWAY, CAGAYAN DE ORO CITY</div>
        <div class="branch-info">PUERTO BRANCH</div>
        <div class="form-title">Pre-Need Life Plan Application Form</div>
    </div>

    <!-- Application Dates -->
    <div class="form-dates">
        <div><strong>Application Date:</strong> January 26, 2025</div>
        <div><strong>Plan Subscription:</strong> January 26, 2025 - January 26, 2026</div>
    </div>

    <!-- Planholder's Name Section -->
    <div class="section-title">Planholder's Information</div>
    <table class="info-table">
        <tr>
            <td class="label-cell">Surname:</td>
            <td class="value-cell">DELA CRUZ</td>
            <td class="label-cell">First Name:</td>
            <td class="value-cell">JUAN</td>
            <td class="label-cell">Middle Name:</td>
            <td class="value-cell">SANTOS</td>
        </tr>
    </table>

    <!-- Personal Details -->
    <table class="info-table">
        <tr>
            <td class="label-cell">Date of Birth:</td>
            <td class="value-cell">JANUARY 15, 1985</td>
            <td class="label-cell">Age:</td>
            <td class="value-cell">40</td>
            <td class="label-cell">Sex:</td>
            <td class="value-cell">MALE</td>
            <td class="label-cell">Nationality:</td>
            <td class="value-cell">FILIPINO</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label-cell">Place of Birth:</td>
            <td class="value-cell" colspan="3">CAGAYAN DE ORO CITY</td>
            <td class="label-cell">Civil Status:</td>
            <td class="value-cell">MARRIED</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label-cell">Occupation:</td>
            <td class="value-cell" colspan="2">ENGINEER</td>
            <td class="label-cell">Office Number:</td>
            <td class="value-cell" colspan="2">088-123-4567</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label-cell">Name of Employer:</td>
            <td class="value-cell" colspan="2">ABC CONSTRUCTION COMPANY</td>
            <td class="label-cell">Employment Status:</td>
            <td class="value-cell" colspan="2">PERMANENT</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label-cell">Office Address:</td>
            <td class="value-cell" colspan="2">123 BUSINESS DISTRICT, CAGAYAN DE ORO CITY</td>
            <td class="label-cell">Email Address:</td>
            <td class="value-cell" colspan="2">juan.delacruz@email.com</td>
        </tr>
    </table>

    <!-- Residential Address Section -->
    <div class="section-title">Residential Address</div>

    <table class="info-table">
        <tr>
            <td class="label-cell">Lot/House No.:</td>
            <td class="value-cell" colspan="3">123</td>
            <td class="label-cell">SSS/GSIS No.:</td>
            <td class="value-cell">12-3456789-0</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label-cell">Street:</td>
            <td class="value-cell" colspan="4">MAIN STREET</td>
            <td class="label-cell">TIN:</td>
            <td class="value-cell">123-456-789-000</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label-cell">Barangay:</td>
            <td class="value-cell" colspan="5">BARANGAY 1</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label-cell">City/Municipality:</td>
            <td class="value-cell" colspan="2">CAGAYAN DE ORO CITY</td>
            <td class="label-cell">Province:</td>
            <td class="value-cell" colspan="2">MISAMIS ORIENTAL</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="label-cell">Zip Code:</td>
            <td class="value-cell" colspan="2">9000</td>
            <td class="label-cell">Contact No.:</td>
            <td class="value-cell" colspan="2">0917-123-4567</td>
        </tr>
    </table>

    <!-- Divider -->
    <div class="divider"></div>

    <!-- Authority to Deduct Section -->
    <div class="authority-section">
        <div class="authority-title">Authority to Deduct</div>
        <div class="authority-subtitle">(OIC PRE-NEED LIFE PLAN PROGRAM)</div>

        <div class="payment-info">
            <div class="payment-date">Payment Date: January 26, 2025</div>
            <div class="payment-text">Please debit or transfer in order of fund availability from my:</div>
            <div class="account-info">CASH - Account No. ________________</div>
            <div class="amount-info">in the amount of ₱180.00 as annual contribution for Pre-need life plan.</div>
        </div>
    </div>

    <!-- Footer Section -->
    <div class="footer-section">
        <div class="footer-signatures">
            <!-- Left side: Applicant -->
            <div class="signature-box">
                <div class="applicant-name">JUAN SANTOS DELA CRUZ</div>
                <div class="signature-line"></div>
                <div class="signature-label">Applicant's Signature & Date</div>
            </div>

            <!-- Right side: Branch Manager -->
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Branch Manager<br>Signature & Date</div>
            </div>
        </div>
    </div>

    <div class="website-info">
        www.orointegrated.coop | Page 1 of 1
    </div>
</div>
</body>
</html>
