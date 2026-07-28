<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Account - {{ $studentName }}</title>
    <style>
        body { font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; margin: 15px; }
        .header { text-align: center; border-bottom: 2px solid #0B3D91; padding-bottom: 8px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 16pt; color: #0B3D91; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 9pt; color: #555; }

        .meta-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .meta-table td { padding: 4px 0; vertical-align: top; }

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
        .text-center { text-align: center; }

        .summary-container { margin-top: 20px; width: 100%; }
        .summary-box { float: right; width: 45%; border: 1px solid #CFE3FF; background-color: #FAFAF5; padding: 10px; }
        .summary-box table { width: 100%; border-collapse: collapse; }
        .summary-box td { padding: 3px 0; font-size: 9pt; }
        .summary-box .grand-total { border-top: 2px solid #0B3D91; font-weight: bold; font-size: 11pt; color: #0B3D91; }

        .clear { clear: both; }
        .footer { margin-top: 40px; font-size: 8pt; color: #8AA8CC; text-align: center; }
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

    <div class="header">
        <h1>Graduate School Office</h1>
        <p>OFFICIAL STUDENT STATEMENT OF ACCOUNT</p>
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>Student Name:</strong> {{ $normalizeText($studentName) }}</td>
            <td class="text-right"><strong>Date Issued:</strong> {{ $generatedAt }}</td>
        </tr>
        <tr>
            <td><strong>Course / Program:</strong> {{ $normalizeText($records->first()->course ?? 'N/A') }}</td>
            <td class="text-right"><strong>Status:</strong> {{ $summary['outstandingBalance'] <= 0 ? 'Settled' : 'Outstanding' }}</td>
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

    <div class="summary-container">
        <div class="summary-box">
            <table>
                <tr>
                    <td>Total Billed Charges (AR):</td>
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

    <div class="footer">
        <p>This is a computer-generated statement. No signature required.</p>
    </div>

</body>
</html>