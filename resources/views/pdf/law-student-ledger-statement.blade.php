<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Account - {{ $studentName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #1a1a1a;
            margin: 10px 12px;
            background: #fff;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #999;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .receipt-header .logo-wrap {
            margin-bottom: 4px;
        }
        .header-logo {
            display: block;
            margin: 0 auto;
            width: 50px;
            height: 50px;
            object-fit: contain;
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

        .ledger-table tbody tr:nth-child(even) {
            background-color: #fafcfe;
        }

        .ledger-table tbody tr.payment-row {
            background-color: #fff8f8;
        }
        .ledger-table tbody tr.payment-row:nth-child(even) {
            background-color: #fff5f5;
        }

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

        .footer {
            margin-top: 12px;
            font-size: 6pt;
            color: #aaa;
            text-align: center;
            border-top: 1px dotted #ddd;
            padding-top: 4px;
        }

        .clear { clear: both; }
        .text-muted { color: #888; }
        .text-success { color: #2e7d32; }
        .text-danger { color: #c62828; }
        .text-bold { font-weight: 700; }
        .text-negative { color: #c62828; }
        .text-positive { color: #2e7d32; }

        @media print {
            body { margin: 8px; }
            .ledger-table th { background-color: #e8edf3 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ledger-table tbody tr:nth-child(even) { background-color: #f5f7fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ledger-table tbody tr.payment-row { background-color: #fff8f8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ledger-table tbody tr.payment-row:nth-child(even) { background-color: #fff5f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    @php
<<<<<<< HEAD
        $normalizeText = static fn ($value) => str_replace(['−', '–', '—'], '-', (string) ($value ?? ''));
        $firstRecord = $records->first();
        $cleanAmount = static fn ($val) => abs((float) preg_replace('/[^\d.]/', '', (string) ($val ?? 0)));

        // Universal helper: read a property regardless of object/array shape
        // (Eloquent model or transformed array).
        $getProp = static function ($obj, array $keys) {
            if (!$obj) {
                return null;
            }
            foreach ($keys as $key) {
                if (is_object($obj) && isset($obj->{$key}) && $obj->{$key} !== '') {
                    return $obj->{$key};
                }
                if (is_array($obj) && isset($obj[$key]) && $obj[$key] !== '') {
                    return $obj[$key];
                }
            }
            return null;
        };

        $courseCode = $firstRecord ? ($getProp($firstRecord, ['course', 'course_code', 'code']) ?? 'N/A') : 'N/A';
        $schoolYear = $firstRecord ? ($getProp($firstRecord, ['schoolYear', 'school_year']) ?? 'N/A') : 'N/A';
        $semesterLabel = $firstRecord ? ($getProp($firstRecord, ['semesterOrSummer', 'semester_or_summer', 'semester']) ?? 'N/A') : 'N/A';
        $units = $firstRecord ? ($getProp($firstRecord, ['units']) ?? 'N/A') : 'N/A';

        $isPayment = static function ($record) use ($getProp) {
            $rawType = strtoupper(trim((string) ($getProp($record, ['arOrPayment', 'ar_or_payment']) ?? '')));
            return in_array($rawType, ['PAYMENT', 'P', 'PAYMENR', 'SETTLED', 'ADJUSTMENT', 'ADJ']);
        };

        $formatAmount = static function ($record) use ($cleanAmount, $isPayment, $getProp) {
            $amount = $cleanAmount($getProp($record, ['amount']) ?? 0);
=======
        $normalizeText = static fn ($value) => str_replace(['−', '–', '—'], '-', (string) $value);
        $firstRecord = $records->first();
        $cleanAmount = static fn ($val) => abs((float) preg_replace('/[^\d.]/', '', (string) $val));

        $isPayment = static fn ($record) => in_array(strtoupper(trim($record->ar_or_payment ?? '')), ['PAYMENT', 'P', 'PAYMENR', 'SETTLED', 'ADJUSTMENT', 'ADJ']);

        $formatAmount = static function ($record) use ($cleanAmount, $isPayment) {
            $amount = $cleanAmount($record->amount ?? 0);
            $type = strtoupper(trim($record->ar_or_payment ?? ''));
>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703

            if ($isPayment($record)) {
                return '-₱' . number_format($amount, 2);
            }

            return '₱' . number_format($amount, 2);
        };

        $getAmountClass = static fn ($record) => $isPayment($record) ? 'text-negative' : 'text-positive';
    @endphp

    <div class="receipt-header">
        @if($logoDataUri)
        <div class="logo-wrap">
            <img class="header-logo" src="{{ $logoDataUri }}" alt="NORSU Logo">
        </div>
        @endif
        <h1>NORSU Law School Office</h1>
        <div class="sub">Official Student Statement of Account</div>
        <div class="divider"></div>
    </div>

    <div class="meta-grid">
        <div class="meta-row">
            <span class="meta-cell label">Student:</span>
            <span class="meta-cell value">{{ $normalizeText($studentName) }}</span>
            <span class="meta-cell label">Date Issued:</span>
            <span class="meta-cell value right">{{ $generatedAt }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-cell label">Course:</span>
<<<<<<< HEAD
            <span class="meta-cell value">{{ $normalizeText($courseCode) }}</span>
=======
            <span class="meta-cell value">{{ $normalizeText($firstRecord->course ?? 'N/A') }}</span>
>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703
            <span class="meta-cell label">Status:</span>
            <span class="meta-cell value right {{ $summary['outstandingBalance'] <= 0 ? 'text-success' : 'text-danger' }}">
                {{ $summary['outstandingBalance'] <= 0 ? '✓ SETTLED' : 'OUTSTANDING' }}
            </span>
        </div>
        <div class="meta-row">
            <span class="meta-cell label">School Year:</span>
<<<<<<< HEAD
            <span class="meta-cell value">{{ $normalizeText($schoolYear) }}</span>
            <span class="meta-cell label">Units:</span>
            <span class="meta-cell value right">{{ $normalizeText($units) }}</span>
        </div>
        @if($semesterLabel !== 'N/A')
        <div class="meta-row">
            <span class="meta-cell label">Semester:</span>
            <span class="meta-cell value">{{ $normalizeText($semesterLabel) }}</span>
=======
            <span class="meta-cell value">{{ $normalizeText($firstRecord->school_year ?? 'N/A') }}</span>
            <span class="meta-cell label">Units:</span>
            <span class="meta-cell value right">{{ $normalizeText($firstRecord->units ?? 'N/A') }}</span>
        </div>
        @if($firstRecord && $firstRecord->semester_or_summer)
        <div class="meta-row">
            <span class="meta-cell label">Semester:</span>
            <span class="meta-cell value">{{ $normalizeText($firstRecord->semester_or_summer) }}</span>
>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703
            <span class="meta-cell label"></span>
            <span class="meta-cell value right"></span>
        </div>
        @endif
    </div>

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
<<<<<<< HEAD
                    @php
                        $txDate = $getProp($r, ['transactionDate', 'transaction_date']);
                        $refNo  = $getProp($r, ['referenceNo', 'reference_jev_or_number']) ?? '-';
                        $part   = $getProp($r, ['particulars']) ?? '-';
                        $rate   = $getProp($r, ['tuitionPerUnitOrFeePerSemester', 'tuition_per_unit_or_fee_per_semester']) ?? 0;
                    @endphp
                    <tr class="{{ $isPayment($r) ? 'payment-row' : '' }}">
                        <td>{{ $normalizeText($txDate ? \Carbon\Carbon::parse($txDate)->format('m/d/Y') : '-') }}</td>
                        <td>{{ $normalizeText($refNo) }}</td>
                        <td>{{ \Str::limit($normalizeText($part), 35) }}</td>
                        <td class="text-right">₱{{ number_format($cleanAmount($rate), 2) }}</td>
=======
                    <tr class="{{ $isPayment($r) ? 'payment-row' : '' }}">
                        <td>{{ $normalizeText($r->transaction_date ? \Carbon\Carbon::parse($r->transaction_date)->format('m/d/Y') : '-') }}</td>
                        <td>{{ $normalizeText($r->reference_jev_or_number ?? '-') }}</td>
                        <td>{{ \Str::limit($normalizeText($r->particulars ?? '-'), 35) }}</td>
                        <td class="text-right">₱{{ number_format($cleanAmount($r->tuition_per_unit_or_fee_per_semester ?? 0), 2) }}</td>
>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703
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

    <div class="footer">
        <span>Generated: {{ now()->timezone('Asia/Manila')->format('Y-m-d h:i A') }} &bull; This is a computer-generated statement. No signature required.</span>
    </div>

</body>
</html>