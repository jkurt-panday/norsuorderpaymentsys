<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Account - {{ $studentName }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; margin: 15px; }
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

    <div class="header">
        <h1>Graduate School Office</h1>
        <p>OFFICIAL STUDENT STATEMENT OF ACCOUNT</p>
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>Student Name:</strong> {{ $studentName }}</td>
            <td class="text-right"><strong>Date Issued:</strong> {{ $generatedAt }}</td>
        </tr>
        <tr>
            <td><strong>Course / Program:</strong> {{ $records->first()->course ?? 'N/A' }}</td>
            <td class="text-right"><strong>Status:</strong> {{ $summary['outstandingBalance'] <= 0 ? 'Settled' : 'Outstanding' }}</td>
        </tr>
    </table>

    <table class="ledger-table">
        <thead>
            <tr>
                <th width="12%">Date</th>
                <th width="15%">S.Y. / Term</th>
                <th width="18%">Ref / OR #</th>
                <th>Particulars</th>
                <th width="12%" class="text-center">Type</th>
                <th width="15%" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $r)
                <tr>
                    <td>{{ $r->transaction_date ? \Carbon\Carbon::parse($r->transaction_date)->format('Y-m-d') : '-' }}</td>
                    <td>{{ $r->school_year }} {{ $r->semester_short ? "({$r->semester_short})" : '' }}</td>
                    <td>{{ $r->reference_or_jev_number ?? '-' }}</td>
                    <td>{{ $r->particulars ?? '-' }}</td>
                    <td class="text-center">{{ strtoupper($r->ar_payment ?? 'AR') }}</td>
                    <td class="text-right">₱{{ number_format(abs((float) preg_replace('/[^\d.]/', '', (string) $r->amount)), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colSpan="6" class="text-center">No transactions on record for this student.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

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