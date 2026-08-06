<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Account - {{ $studentName }}</title>
    <style>
<<<<<<< HEAD
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            font-size: 8pt; 
            color: #1a1a1a; 
            margin: 10px 12px;
            background: #fff;
        }

        /* Receipt-style header */
        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #999;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .receipt-header h1 {
            font-size: 12pt;
            color: #0B3D91;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .receipt-header .sub {
            font-size: 7pt;
            color: #666;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .receipt-header .divider {
            border-top: 1px dotted #ccc;
            margin: 4px 0;
        }

        /* Compact meta info */
        .meta-grid {
            display: table;
            width: 100%;
            margin-bottom: 6px;
            font-size: 7.5pt;
        }
        .meta-row {
            display: table-row;
        }
        .meta-cell {
            display: table-cell;
            padding: 1px 0;
        }
        .meta-cell.label {
            color: #555;
            width: 18%;
        }
        .meta-cell.value {
            font-weight: 600;
            width: 32%;
        }
        .meta-cell.right {
            text-align: right;
        }

        /* Compact table */
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-top: 4px;
        }
        .ledger-table th {
            background-color: #f0f4f8;
            color: #0B3D91;
            font-size: 6.5pt;
            text-transform: uppercase;
            border: 1px solid #dde4ec;
            padding: 3px 4px;
            letter-spacing: 0.5px;
        }
        .ledger-table td {
            border: 1px solid #e8edf3;
            padding: 3px 4px;
        }
        .ledger-table .text-right { text-align: right; }
        .ledger-table .text-center { text-align: center; }

        /* Alternating row colors for readability */
        .ledger-table tbody tr:nth-child(even) {
            background-color: #fafcfe;
        }

        /* Payment rows with subtle red tint */
        .ledger-table tbody tr.payment-row {
            background-color: #fff8f8;
        }
        .ledger-table tbody tr.payment-row:nth-child(even) {
            background-color: #fff5f5;
        }

        /* Summary box - receipt style */
        .summary-container {
            margin-top: 10px;
            border-top: 1px dashed #999;
            padding-top: 8px;
        }
        .summary-grid {
            display: table;
            width: 100%;
            font-size: 7.5pt;
        }
        .summary-row {
            display: table-row;
        }
        .summary-cell {
            display: table-cell;
            padding: 1px 0;
        }
        .summary-cell.label {
            color: #555;
            width: 70%;
        }
        .summary-cell.value {
            text-align: right;
            font-weight: 500;
            width: 30%;
        }
        .summary-row.total .summary-cell {
            border-top: 2px solid #0B3D91;
            padding-top: 4px;
            font-weight: 700;
            font-size: 9pt;
            color: #0B3D91;
        }

        /* Footer - tiny */
        .footer {
            margin-top: 12px;
            font-size: 6pt;
            color: #aaa;
            text-align: center;
            border-top: 1px dotted #ddd;
            padding-top: 4px;
        }

        /* Utility */
        .clear { clear: both; }
        .text-muted { color: #888; }
        .text-success { color: #2e7d32; }
        .text-danger { color: #c62828; }
        .text-bold { font-weight: 700; }
        .text-negative { color: #c62828; }
        .text-positive { color: #2e7d32; }

        /* Print-specific */
        @media print {
            body { margin: 8px; }
            .ledger-table th { background-color: #e8edf3 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ledger-table tbody tr:nth-child(even) { background-color: #f5f7fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ledger-table tbody tr.payment-row { background-color: #fff8f8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ledger-table tbody tr.payment-row:nth-child(even) { background-color: #fff5f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
=======
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 10mm 12mm;
        }
        
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            line-height: 1.3;
        }

        /* Top Header */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0B3D91;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .header-title {
            font-size: 14pt;
            font-weight: bold;
            color: #0B3D91;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-subtitle {
            font-size: 8pt;
            color: #4A5568;
            margin: 2px 0 0 0;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* Student Metadata Card */
        .meta-container {
            width: 100%;
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2px 0;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .label {
            color: #475569;
            font-weight: bold;
            width: 110px;
        }
        .value {
            color: #0F172A;
        }

        /* Semester Section Header */
        .term-header {
            background-color: #0B3D91;
            color: #FFFFFF;
            font-size: 8.5pt;
            font-weight: bold;
            padding: 4px 8px;
            margin-top: 8px;
            margin-bottom: 0;
            border-radius: 3px 3px 0 0;
            letter-spacing: 0.5px;
        }

        /* Main Ledger Table */
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .ledger-table th {
            background-color: #F1F5F9;
            color: #334155;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 6px;
            border: 1px solid #CBD5E1;
            text-align: left;
        }
        .ledger-table td {
            border: 1px solid #E2E8F0;
            padding: 4px 6px;
            font-size: 8pt;
        }
        .ledger-table tr:nth-child(even) {
            background-color: #F8FAFC;
        }

        /* Subtotal Row */
        .term-subtotal {
            background-color: #F8FAFC;
            border: 1px solid #CBD5E1;
            border-top: none;
            padding: 4px 8px;
            font-size: 8pt;
            font-weight: bold;
            color: #1E293B;
            margin-bottom: 8px;
            border-radius: 0 0 3px 3px;
        }

        .text-right { text-align: right; }
        .text-center { text-center: center; }

        .badge-settled {
            color: #166534;
            font-weight: bold;
        }
        .badge-outstanding {
            color: #991B1B;
            font-weight: bold;
        }

        /* Bottom Section: Signatures & Summary */
        .bottom-section {
            margin-top: 10px;
            width: 100%;
        }
        .signature-box {
            float: left;
            width: 48%;
            font-size: 8pt;
            margin-top: 8px;
        }
        .signature-line {
            border-bottom: 1px solid #64748B;
            width: 180px;
            margin-top: 22px;
            margin-bottom: 3px;
        }
        
        .summary-box {
            float: right;
            width: 45%;
            border: 1px solid #CBD5E1;
            background-color: #F8FAFC;
            padding: 6px 10px;
            border-radius: 4px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 2px 0;
            font-size: 8.5pt;
        }
        .summary-table .grand-total {
            border-top: 1.5px solid #0B3D91;
            font-weight: bold;
            font-size: 9.5pt;
            color: #0B3D91;
            padding-top: 3px;
        }

        .clear { clear: both; }

        .footer-note {
            margin-top: 10px;
            font-size: 7.5pt;
            color: #94A3B8;
            text-align: center;
            border-top: 1px dashed #E2E8F0;
            padding-top: 4px;
>>>>>>> origin
        }
    </style>
</head>
<body>

    @php
        $normalizeText = static fn ($value) => str_replace(['−', '–', '—'], '-', (string) $value);
<<<<<<< HEAD
        $firstRecord = $records->first();
        $cleanAmount = static fn ($val) => abs((float) preg_replace('/[^\d.]/', '', (string) $val));
        
        // Helper to determine if a record is a payment
        $isPayment = static fn ($record) => in_array(strtoupper(trim($record->ar_payment ?? '')), ['PAYMENT', 'P', 'PAYMENR', 'SETTLED', 'ADJUSTMENT', 'ADJ']);
        
        // Helper to format amount with sign
        $formatAmount = static function ($record) use ($cleanAmount, $isPayment) {
            $amount = $cleanAmount($record->amount ?? 0);
            $type = strtoupper(trim($record->ar_payment ?? ''));
            
            // If it's a payment, show with negative sign
            if ($isPayment($record)) {
                return '-₱' . number_format($amount, 2);
            }
            
            // If it's an assessment (AR), show as positive
            return '₱' . number_format($amount, 2);
        };
        
        // Helper to get amount class
        $getAmountClass = static fn ($record) => $isPayment($record) ? 'text-negative' : 'text-positive';
    @endphp

    <!-- RECEIPT HEADER -->
    <div class="receipt-header">
        <h1>NORSU Graduate School</h1>
        <div class="sub">Official Student Statement of Account</div>
        <div class="divider"></div>
    </div>

    <!-- COMPACT META INFO -->
    <div class="meta-grid">
        <div class="meta-row">
            <span class="meta-cell label">Student:</span>
            <span class="meta-cell value">{{ $normalizeText($studentName) }}</span>
            <span class="meta-cell label">Date Issued:</span>
            <span class="meta-cell value right">{{ $generatedAt }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-cell label">Course:</span>
            <span class="meta-cell value">{{ $normalizeText($firstRecord->course ?? 'N/A') }}</span>
            <span class="meta-cell label">Status:</span>
            <span class="meta-cell value right {{ $summary['outstandingBalance'] <= 0 ? 'text-success' : 'text-danger' }}">
                {{ $summary['outstandingBalance'] <= 0 ? '✓ SETTLED' : 'OUTSTANDING' }}
            </span>
        </div>
        <div class="meta-row">
            <span class="meta-cell label">School Year:</span>
            <span class="meta-cell value">{{ $normalizeText($firstRecord->school_year ?? 'N/A') }}</span>
            <span class="meta-cell label">Units:</span>
            <span class="meta-cell value right">{{ $normalizeText($firstRecord->units ?? 'N/A') }}</span>
        </div>
        @if($firstRecord && ($firstRecord->semester_short || $firstRecord->semester))
        <div class="meta-row">
            <span class="meta-cell label">Semester:</span>
            <span class="meta-cell value">{{ $normalizeText($firstRecord->semester_short ?? $firstRecord->semester) }}</span>
            <span class="meta-cell label"></span>
            <span class="meta-cell value right"></span>
        </div>
        @endif
    </div>

    <!-- LEDGER TABLE -->
    <table class="ledger-table">
        <thead>
            <tr>
                <th width="11%">Date</th>
                <th width="14%">Ref #</th>
                <th width="28%">Particulars</th>
                <th width="14%" class="text-right">Rate</th>
                <th width="10%" class="text-center">Type</th>
                <th width="13%" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @if($records->isNotEmpty())
                @foreach($records as $r)
                    <tr class="{{ $isPayment($r) ? 'payment-row' : '' }}">
                        <td>{{ $normalizeText($r->transaction_date ? \Carbon\Carbon::parse($r->transaction_date)->format('m/d/Y') : '-') }}</td>
                        <td>{{ $normalizeText($r->reference_or_jev_number ?? '-') }}</td>
                        <td>{{ Str::limit($normalizeText($r->particulars ?? '-'), 35) }}</td>
                        <td class="text-right">₱{{ number_format($cleanAmount($r->tuition_per_unit_or_misc ?? 0), 2) }}</td>
                        <td class="text-center">
                            {{ $isPayment($r) ? 'PAY' : 'AR' }}
                        </td>
                        <td class="text-right {{ $getAmountClass($r) }}">
                            {{ $formatAmount($r) }}
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="6" class="text-center text-muted" style="padding: 8px 0;">No transactions on record.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- SUMMARY - RECEIPT STYLE -->
    <div class="summary-container">
        <div class="summary-grid">
            <div class="summary-row">
                <span class="summary-cell label">Total Assessments:</span>
                <span class="summary-cell value text-positive">₱{{ number_format($summary['totalCharges'], 2) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-cell label">Total Payments / Credits:</span>
                <span class="summary-cell value text-negative">-₱{{ number_format($summary['totalPayments'], 2) }}</span>
            </div>
            <div class="summary-row total">
                <span class="summary-cell label">OUTSTANDING BALANCE:</span>
                <span class="summary-cell value {{ $summary['outstandingBalance'] <= 0 ? 'text-success' : 'text-danger' }}">
                    ₱{{ number_format($summary['outstandingBalance'], 2) }}
                </span>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <span>Generated: {{ now()->format('Y-m-d h:i A') }} &bull; This is a computer-generated statement. No signature required.</span>
    </div>

</body>
</html>
=======

        // Group records by School Year & Semester
        $groupedRecords = $records->groupBy(function ($r) {
            $sy = trim($r->school_year ?? '');
            $sem = trim($r->semester_short ?: ($r->semester ?: ''));
            if (!$sy && !$sem) return 'General Transactions';
            return implode(' — ', array_filter([$sy, $sem]));
        });
    @endphp

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td>
                <h1 class="header-title">NORSU Graduate School</h1>
                <p class="header-subtitle">Official Student Statement of Account</p>
            </td>
            <td class="text-right" style="vertical-align: bottom;">
                <span style="font-size: 8pt; color: #64748B;">Date Generated: <strong>{{ $generatedAt }}</strong></span>
            </td>
        </tr>
    </table>

    <!-- Student Metadata -->
    <div class="meta-container">
        <table class="meta-table">
            <tr>
                <td class="label">Student Name:</td>
                <td class="value"><strong>{{ $normalizeText($studentName) }}</strong></td>
                <td class="label" style="text-align: right; width: 80px;">Account Status:</td>
                <td class="value text-right" style="width: 100px;">
                    @if($summary['outstandingBalance'] <= 0)
                        <span class="badge-settled">PAID / SETTLED</span>
                    @else
                        <span class="badge-outstanding">OUTSTANDING</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Course / Program:</td>
                <td class="value">{{ $normalizeText($records->first()->course ?? 'N/A') }}</td>
                <td class="label" style="text-align: right;">Total Records:</td>
                <td class="value text-right">{{ $records->count() }} transaction(s)</td>
            </tr>
        </table>
    </div>

    <!-- Grouped Ledger Tables per Semester -->
    @forelse($groupedRecords as $termName => $termRecords)
        @php
            $termCharges = 0;
            $termPayments = 0;

            foreach ($termRecords as $tr) {
                $amt = abs((float) preg_replace('/[^\d.]/', '', (string) $tr->amount));
                $type = strtoupper(trim($tr->ar_payment ?? 'AR'));
                if ($type === 'AR') {
                    $termCharges += $amt;
                } else {
                    $termPayments += $amt;
                }
            }
            $termBalance = $termCharges - $termPayments;
        @endphp

        <div class="term-header">
            Academic Term: {{ $normalizeText($termName) }}
        </div>

        <table class="ledger-table">
            <thead>
                <tr>
                    <th width="15%">Date</th>
                    <th width="20%">OR / JEV #</th>
                    <th>Particulars</th>
                    <th width="12%" class="text-center">Type</th>
                    <th width="18%" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($termRecords as $r)
                    <tr>
                        <td>{{ $normalizeText($r->transaction_date ? \Carbon\Carbon::parse($r->transaction_date)->format('Y-m-d') : '-') }}</td>
                        <td>{{ $normalizeText($r->reference_or_jev_number ?? '-') }}</td>
                        <td>{{ $normalizeText($r->particulars ?? '-') }}</td>
                        <td class="text-center">{{ $normalizeText(strtoupper($r->ar_payment ?? 'AR')) }}</td>
                        <td class="text-right">
                            @php
                                $amt = abs((float) preg_replace('/[^\d.]/', '', (string) $r->amount));
                                $type = strtoupper(trim($r->ar_payment ?? 'AR'));
                                $isCredit = in_array($type, ['PAYMENT', 'P', 'ADJUSTMENT', 'ADJ']);
                            @endphp
                            @if($isCredit)
                                <span style="color: #166534;">(₱{{ number_format($amt, 2) }})</span>
                            @else
                                ₱{{ number_format($amt, 2) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="term-subtotal">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td>Term Summary:</td>
                    <td class="text-right" style="font-weight: normal; color: #475569;">
                        Billed: <strong>₱{{ number_format($termCharges, 2) }}</strong> &nbsp;|&nbsp;
                        Paid: <strong>₱{{ number_format($termPayments, 2) }}</strong> &nbsp;|&nbsp;
                        Term Balance: <strong style="color: {{ $termBalance > 0 ? '#991B1B' : '#166534' }};">₱{{ number_format($termBalance, 2) }}</strong>
                    </td>
                </tr>
            </table>
        </div>
    @empty
        <div style="text-align: center; padding: 20px; color: #64748B; border: 1px solid #E2E8F0; border-radius: 4px;">
            No transactions on record for this student.
        </div>
    @endforelse

    <!-- Bottom Summary & Signatures -->
    <div class="bottom-section">
        <div class="signature-box">
            <p style="margin: 0; color: #475569;">Prepared & Checked by:</p>
            <div class="signature-line"></div>
            <p style="margin: 0; font-weight: bold; color: #1E293B;">Graduate School Accountant</p>
        </div>

        <div class="summary-box">
            <table class="summary-table">
                <tr>
                    <td>Total Billed (AR):</td>
                    <td class="text-right">₱{{ number_format($summary['totalCharges'], 2) }}</td>
                </tr>
                <tr>
                    <td>Total Payments / Credits:</td>
                    <td class="text-right">(₱{{ number_format($summary['totalPayments'], 2) }})</td>
                </tr>
                <tr class="grand-total">
                    <td>Outstanding Balance:</td>
                    <td class="text-right">₱{{ number_format($summary['outstandingBalance'], 2) }}</td>
                </tr>
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <!-- Footer Note -->
    <div class="footer-note">
        This is an official computer-generated statement of account from the NORSU Order & Payment System.
    </div>

</body>
</html>


>>>>>>> origin
