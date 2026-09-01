<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Statement of Account - {{ $assessment->reference_number }}</title>
        @vite(['resources/css/app.css'])
    </head>
<body class="text-[11px] text-gray-900 font-sans">

    @php
        // Base64-embed the header image so it renders regardless of PDF engine.
        $headerImagePath = resource_path('views/pdf/norsu header.png');
        $headerImageBase64 = file_exists($headerImagePath)
            ? base64_encode(file_get_contents($headerImagePath))
            : null;

        // ── Real shape, from LedgerMatchingService::statementPayload() ──
        $selectedStudent = $ledgerStatement['selectedStudent'] ?? null;
        $records         = $ledgerStatement['records'] ?? collect();
        $summary         = $ledgerStatement['summary'] ?? [
            'totalCharges' => 0,
            'totalPayments' => 0,
            'outstandingBalance' => 0,
        ];
        $schoolYear = $ledgerStatement['schoolYear'] ?? '—';
        $semester   = $ledgerStatement['semester'] ?? '—';

        // No "address" field exists anywhere in this payload or on AssessmentForm as loaded —
        // left out of the layout below. Add it back in if you have a real source for it.
        $studentName = $selectedStudent['name']
            ?? trim(($assessment->first_name ?? '') . ' ' . ($assessment->last_name ?? '')) ?: '—';
        $studentId   = $assessment['student_id'] ?? '—';
        $courseTitle = $assessment->course->course_desc ?? $assessment->course->code ?? '—';

        // "Units" isn't present in Assessment as loaded (only ->load(['course'])) or in the
        // ledger payload — guessing $assessment->units exists; confirm and adjust if not.
        $units = $assessment->units ?? '—';

        $formNumber = $assessment->reference_number ?? $assessment->id;
    @endphp

    @if($headerImageBase64)
        <div class="w-full mb-2">
            <img
                class="w-full"
                src="data:image/png;base64,{{ $headerImageBase64 }}"
                alt="NORSU Header"
            >
        </div>
    @endif

        {{-- <pre>{{ json_encode(get_defined_vars(), JSON_PRETTY_PRINT) }}</pre> --}}
    
    <h1 class="text-center font-bold text-2xl my-4">Statement of Account</h1>

    <table class="w-full mb-3 text-lg">
        <tr>
            <td class="font-bold w-28 align-top py-0.5">Name:</td>
            <td class="italic align-top py-0.5">{{ $studentName }}</td>
            <td class="font-bold w-36 align-top py-0.5">Assessment Form No:</td>
            <td class="italic align-top py-0.5">{{ $formNumber }}</td>
        </tr>
        <tr>
            <td class="font-bold align-top py-0.5">Student ID:</td>
            <td class="italic align-top py-0.5">{{ $studentId }}</td>
            <td class="font-bold align-top py-0.5">Semester:</td>
            <td class="italic align-top py-0.5">{{ $semester }}</td>
        </tr>
        <tr>
            <td class="font-bold align-top py-0.5">Course:</td>
            <td class="italic align-top py-0.5">{{ $courseTitle }}</td>
            <td class="font-bold align-top py-0.5">School Year:</td>
            <td class="italic align-top py-0.5">{{ $schoolYear }}</td>
        </tr>
        <tr>
            <td class="font-bold align-top py-0.5">Units:</td>
            <td class="italic align-top py-0.5">{{ $units }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <table class="w-full border border-black border-collapse mt-1.5 text-[1rem]">
        <thead>
            <tr class="border-b-2 border-black">
                <th class="text-left px-1.5 py-1 w-[22%]">Date</th>
                <th class="text-left px-1.5 py-1 w-[14%]">Ref #</th>
                <th class="text-left px-1.5 py-1 w-[24%]">Particulars</th>
                <th class="text-left px-1.5 py-1 w-[16%]">Type</th>
                <th class="text-right px-1.5 py-1 w-[24%]">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                @php
                    // amount is always positive/abs in the payload — 'type' carries the sign meaning.
                    $isPayment = ($record['type'] ?? '') === 'Payment';
                    $amountValue = (float) ($record['amount'] ?? 0);
                    $amountDisplay = $isPayment
                        ? '(' . number_format($amountValue, 2) . ')'
                        : number_format($amountValue, 2);
                @endphp
                <tr>
                    <td class="px-1.5 py-1">{{ $record['transactionDate'] ?? '—' }}</td>
                    <td class="px-1.5 py-1">{{ $record['referenceNo'] ?? '' }}</td>
                    <td class="px-1.5 py-1">{{ $record['particulars'] ?? '—' }}</td>
                    <td class="px-1.5 py-1">{{ $record['type'] ?? '—' }}</td>
                    <td class="text-right px-1.5 py-1">{{ $amountDisplay }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="italic text-gray-500 px-1.5 py-3">No matching ledger records found.</td>
                </tr>
            @endforelse

            <tr class="border-t-2 border-black font-bold">
                <td colspan="3"></td>
                <td class="text-right px-1.5 py-1.5">Balance</td>
                <td class="text-right px-1.5 py-1.5">{{ number_format($summary['outstandingBalance'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="w-full mt-10 text-lg">
        <tr>
            <td class="w-1/2 align-top px-2.5">
                Prepared By
    
                <div class="text-center">
                    (SGD)
                </div>
    
                <div class="font-bold mt-6">
                    Donna Belle C. Tawing
                </div>
    
                <div class="mt-1">
                    Accounting Staff
                </div>
    
                <div class="mt-3.5">
                    Date: {{ date('n/j/Y') }}
                </div>
            </td>
    
            <td class="w-1/2 align-top px-2.5">
                Certified Correct
    
                <div class="text-center">
                    (SGD)
                </div>
    
                <div class="font-bold mt-6">
                    Maurice Anaver B. Dordado, CPA
                </div>

                <div class="mt-1">
                    Head of Accounting/Division/Unit
                </div>
    
                <div class="mt-1">
                    Authorized Official
                </div>
    
                <div class="mt-3.5">
                    Date: {{ date('n/j/Y') }}
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
