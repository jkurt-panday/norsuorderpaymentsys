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

        .ledger-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .ledger-table th { background-color: #F3F8FF; color: #0B3D91; font-size: 8pt; text-transform: uppercase; border: 1px solid #CFE3FF; padding: 6px; }
        .ledger-table td { border: 1px solid #EAF2FF; padding: 5px; font-size: 9pt; }
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
    @endphp

    <div class="header">
        <h1>Law School Office</h1>
        <p>OFFICIAL STUDENT STATEMENT OF ACCOUNT</p>
    </div>

    @php
        $firstRecord = $records->first();
        // Debug info
        $debugInfo = [
            'studentName' => $studentName,
            'recordsCount' => $records->count(),
            'hasFirstRecord' => $firstRecord !== null,
            'firstRecordData' => $firstRecord ? $firstRecord->toArray() : null
        ];
    @endphp
    
    <table class="meta-table">
        <tr>
            <td><strong>Student Name:</strong> {{ $normalizeText($studentName) }}</td>
            <td class="text-right"><strong>Date Issued:</strong> {{ $generatedAt }}</td>
        </tr>
        <tr>
            <td><strong>Course:</strong> {{ $normalizeText($firstRecord->course ?? 'N/A') }}</td>
            <td class="text-right"><strong>Status:</strong> {{ $summary['outstandingBalance'] <= 0 ? 'Settled' : 'Outstanding' }}</td>
        </tr>
        <tr>
            <td><strong>School Year:</strong> {{ $normalizeText($firstRecord->school_year ?? 'N/A') }} {{ $firstRecord && $firstRecord->semester_or_summer ? "({$firstRecord->semester_or_summer})" : '' }}</td>
            <td class="text-right"><strong>Units:</strong> {{ $normalizeText($firstRecord->units ?? 'N/A') }}</td>
        </tr>
    </table>

    <table class="ledger-table">
        <thead>
            <tr>
                <th width="12%">Date</th>
                <th width="15%">Ref / JEV #</th>
                <th>Particulars</th>
                <th width="12%">Tuition/Unit or Reg. & Misc. Fee</th>
                <th width="10%" class="text-center">AR/Payment</th>
                <th width="12%" class="text-right">Amount</th>
<<<<<<< HEAD
=======
                <th width="10%" class="text-center">Remarks</th>
>>>>>>> origin
            </tr>
        </thead>
        <tbody>
            @if($records->isNotEmpty())
                @foreach($records as $r)
                    <tr>
                        <td>{{ $normalizeText($r->transaction_date ? \Carbon\Carbon::parse($r->transaction_date)->format('Y-m-d') : '-') }}</td>
                        <td>{{ $normalizeText($r->reference_jev_or_number ?? '-') }}</td>
                        <td>{{ $normalizeText($r->particulars ?? '-') }}</td>
                        <td class="text-right">₱{{ number_format(abs((float) preg_replace('/[^\d.]/', '', (string) ($r->tuition_per_unit_or_fee_per_semester ?? 0))), 2) }}</td>
                        <td class="text-center">{{ $normalizeText(strtoupper($r->ar_or_payment ?? 'AR')) }}</td>
                        <td class="text-right">₱{{ number_format(abs((float) preg_replace('/[^\d.]/', '', (string) ($r->amount ?? 0))), 2) }}</td>
<<<<<<< HEAD
=======
                        <td class="text-center">{{ $summary['outstandingBalance'] <= 0 ? 'Settled' : 'Outstanding' }}</td>
>>>>>>> origin
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colSpan="6" class="text-center">No transactions on record for this student.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="summary-container">
        <div class="summary-box">
            <table>
                <tr>
                    <td>Total Assessments:</td>
                    <td class="text-right">₱{{ number_format($summary['totalAssessments'], 2) }}</td>
                </tr>
                <tr>
                    <td>Total Payments / Credits:</td>
                    <td class="text-right">₱{{ number_format($summary['totalPayments'], 2) }}</td>
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
