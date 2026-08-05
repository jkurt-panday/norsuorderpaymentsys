<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Account - {{ $studentName }}</title>
    <style>
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

        .semester-block { margin-bottom: 14px; }
        .semester-header {
            background-color: #F3F8FF;
            color: #0B3D91;
            font-size: 9pt;
            font-weight: bold;
            padding: 6px 8px;
            border: 1px solid #CFE3FF;
            border-bottom: none;
        }
        .section-table { width: 100%; border-collapse: collapse; }
        .section-table td { border: 1px solid #EAF2FF; padding: 5px 8px; font-size: 9pt; }
        .section-label {
            background-color: #FAFAFA;
            color: #666;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .subtotal-row td { font-weight: bold; background-color: #FBFDFF; }
        .no-transactions td { text-align: center; color: #888; }

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
        }
    </style>
</head>
<body>

    @php
        $normalizeText = static fn ($value) => str_replace(['−', '–', '—'], '-', (string) $value);
        $parseAmount = static fn ($value) => abs((float) preg_replace('/[^\d.]/', '', (string) $value));

        // Group records by school year + semester, preserving original order
        $semesterGroups = $records->groupBy(function ($r) use ($normalizeText) {
            return $normalizeText($r->school_year) . '||' . $normalizeText($r->semester_short);
        });
    @endphp

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

    @forelse($semesterGroups as $groupKey => $groupRecords)
        @php
            [$schoolYear, $semesterShort] = array_pad(explode('||', $groupKey), 2, '');
            $semesterLabel = trim($schoolYear . ($semesterShort ? " ({$semesterShort})" : ''));

            $charges  = $groupRecords->filter(fn ($r) => strtoupper($r->ar_payment ?? 'AR') === 'AR');
            $payments = $groupRecords->filter(fn ($r) => strtoupper($r->ar_payment ?? 'AR') === 'PAYMENT');

            $chargesSubtotal  = $charges->sum(fn ($r) => $parseAmount($r->amount));
            $paymentsSubtotal = $payments->sum(fn ($r) => $parseAmount($r->amount));

            // Group payments by reference/OR number so the same receipt isn't repeated per line
            $paymentBatches = $payments->groupBy(fn ($r) => $normalizeText($r->reference_or_jev_number ?? '-'));
        @endphp

        <div class="semester-block">
            <div class="semester-header">S.Y. {{ $normalizeText($semesterLabel) }}</div>

            @if($charges->isEmpty() && $payments->isEmpty())
                <table class="section-table">
                    <tr class="no-transactions">
                        <td>No transactions on record</td>
                        <td class="text-right" style="width:15%;">₱0.00</td>
                    </tr>
                </table>
            @else
                @if($charges->isNotEmpty())
                    <table class="section-table">
                        <tr>
                            <td class="section-label" colspan="2">Charges</td>
                        </tr>
                        @foreach($charges as $r)
                            <tr>
                                <td>{{ $normalizeText($r->particulars ?? '-') }}</td>
                                <td class="text-right" style="width:15%;">₱{{ number_format($parseAmount($r->amount), 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="subtotal-row">
                            <td>Subtotal charges</td>
                            <td class="text-right">₱{{ number_format($chargesSubtotal, 2) }}</td>
                        </tr>
                    </table>
                @endif

                @if($payments->isNotEmpty())
                    @foreach($paymentBatches as $refNumber => $batch)
                        @php $batchSubtotal = $batch->sum(fn ($r) => $parseAmount($r->amount)); @endphp
                        <table class="section-table" style="margin-top:6px;">
                            <tr>
                                <td class="section-label" colspan="2">Payments &middot; OR # {{ $refNumber }}</td>
                            </tr>
                            @foreach($batch as $r)
                                <tr>
                                    <td>{{ $normalizeText($r->particulars ?? '-') }}</td>
                                    <td class="text-right" style="width:15%;">(₱{{ number_format($parseAmount($r->amount), 2) }})</td>
                                </tr>
                            @endforeach
                            <tr class="subtotal-row">
                                <td>Subtotal payments</td>
                                <td class="text-right">(₱{{ number_format($batchSubtotal, 2) }})</td>
                            </tr>
                        </table>
                    @endforeach
                @endif
            @endif
        </div>
    @empty
        <table class="section-table">
            <tr class="no-transactions">
                <td colspan="2">No transactions on record for this student.</td>
            </tr>
        </table>
    @endforelse

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
