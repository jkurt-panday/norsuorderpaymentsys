<!DOCTYPE html>
<html>
    <head>
        @php
            // ── Variable & Logic Extraction (Retained from snippet) ──
            $normalizeText = static fn ($value) => str_replace(['−', '–', '—'], '-', (string) ($value ?? ''));
            $firstRecord   = $records->first();
            $cleanAmount   = static fn ($val) => abs((float) preg_replace('/[^\d.]/', '', (string) ($val ?? 0)));

            // Universal property extractor
            $getProp = static function ($obj, array $keys) {
                if (!$obj) return null;
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

            $courseCode    = $firstRecord ? ($getProp($firstRecord, ['course', 'course_code', 'code']) ?? '—') : '—';
            $schoolYear    = $firstRecord ? ($getProp($firstRecord, ['schoolYear', 'school_year']) ?? '—') : '—';
            $semesterLabel = $firstRecord ? ($getProp($firstRecord, ['semesterOrSummer', 'semester_or_summer', 'semester']) ?? '—') : '—';
            $units         = $firstRecord ? ($getProp($firstRecord, ['units']) ?? '—') : '—';
            $studentId     = $assessment['student_id'] ?? ($getProp($firstRecord, ['student_id', 'studentNo']) ?? '—');
            $formNumber    = $assessment->reference_number ?? ($assessment->id ?? '—');
            $studentName   = $studentName ?? (is_object($student ?? null) ? ($student->full_name ?? ($student->name ?? '—')) : '—');

            // Payment determination logic
            $isPayment = static function ($record) use ($getProp) {
                $rawType = strtoupper(trim((string) ($getProp($record, ['arOrPayment', 'ar_or_payment', 'arPayment', 'entry_type']) ?? '')));
                return in_array($rawType, ['PAYMENT', 'P', 'PAYMENR', 'SETTLED', 'ADJUSTMENT', 'ADJ']);
            };

            // Amount formatting logic
            $formatAmount = static function ($record) use ($cleanAmount, $isPayment, $getProp) {
                $amount = $cleanAmount($getProp($record, ['amount']) ?? 0);

                if ($isPayment($record)) {
                    return '- ' . number_format($amount, 2);
                }

                return number_format($amount, 2);
            };

            // Header image processing
            $headerImagePath = resource_path('views/pdf/norsu header.png');
            $headerImageBase64 = file_exists($headerImagePath)
                ? base64_encode(file_get_contents($headerImagePath))
                : ($logoDataUri ?? null);

            // Signatory & user metadata
            $user = $preparedBy ?? '—';
            $official = activeAuthorizedOfficial();
            $signatoryName = $official?->name ?? 'Maurice Anaver B. Dordado, CPA';
            $authofficialcourse = $official?->course ?? 'CPA';
            $signatoryPosition = $official?->position ?? 'Head of Accounting/Division/Unit';
        @endphp

        <meta charset="utf-8">
        <title>Statement of Account - {{ $studentName }}</title>
        @vite(['resources/css/app.css'])
    </head>
<body class="text-[11px] w-full min-w-[800px] text-gray-900 font-sans">

    {{-- <pre>{{ json_encode(get_defined_vars(), JSON_PRETTY_PRINT) }}</pre> --}}

    @if($headerImageBase64)
        <div class="flex justify-center mb-2">
            <img
                class="w-full max-w-[605px]"
                src="{{ str_starts_with($headerImageBase64, 'data:') ? $headerImageBase64 : 'data:image/png;base64,' . $headerImageBase64 }}"
                alt="NORSU Header"
            >
        </div>
    @endif

    <h1 class="text-center font-bold text-2xl my-4">Statement of Account</h1>

    <!-- Meta Information Block -->
    <table class="w-full mb-3 text-[16px]">
        <tr>
            <td class="font-bold w-28 align-top py-0.5">Name:</td>
            <td class="italic align-top py-0.5">{{ $normalizeText($studentName) }}</td>
            <td class="font-bold w-45 align-top py-0.5">Assessment Form No:</td>
            <td class="italic align-top py-0.5">{{ $formNumber }}</td>
        </tr>
        <tr>
            <td class="font-bold align-top py-0.5">Student ID:</td>
            <td class="italic align-top py-0.5">{{ $studentId }}</td>
            <td class="font-bold align-top py-0.5">Semester:</td>
            <td class="italic align-top py-0.5">{{ $normalizeText($semesterLabel) }}</td>
        </tr>
        <tr>
            <td class="font-bold align-top py-0.5">Course:</td>
            <td class="italic align-top py-0.5">{{ $normalizeText($courseCode) }}</td>
            <td class="font-bold align-top py-0.5">School Year:</td>
            <td class="italic align-top py-0.5">{{ $normalizeText($schoolYear) }}</td>
        </tr>
        <tr>
            <td class="font-bold align-top py-0.5">Units:</td>
            <td class="italic align-top py-0.5">{{ $normalizeText($units) }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <!-- Ledger Table Layout -->
    <table class="w-full border-b border-t border-black border-collapse mt-1.5 text-[1rem]">
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
            @forelse($records as $r)
                @php
                    $txDate = $getProp($r, ['transactionDate', 'transaction_date']);
                    $refNo  = $getProp($r, ['referenceNo', 'reference_jev_or_number']) ?? '';
                    $part   = $getProp($r, ['particulars']) ?? '—';
                    $type   = $isPayment($r) ? 'Payment' : ($getProp($r, ['type', 'entry_type', 'arOrPayment']) ?? 'Charge');
                @endphp
                <tr class="border-b">
                    <td class="px-1.5 py-1">{{ $normalizeText($txDate ? \Carbon\Carbon::parse($txDate)->format('m/d/Y') : '—') }}</td>
                    <td class="px-1.5 py-1">{{ $normalizeText($refNo) }}</td>
                    <td class="px-1.5 py-1">{{ $normalizeText($part) }}</td>
                    <td class="px-1.5 py-1">{{ $type }}</td>
                    <td class="text-right px-1.5 py-1">{{ $formatAmount($r) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="italic text-gray-500 px-1.5 py-3">No matching ledger records found.</td>
                </tr>
            @endforelse

            <tr class="border-t-2 border-black font-bold">
                <td colspan="3"></td>
                <td class="text-right px-1.5 py-1.5">Outstanding Balance</td>
                <td class="text-right px-1.5 py-1.5">{{ number_format($summary['outstandingBalance'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Dual Signatory Section -->
    <table class="w-full mt-20 text-lg">
        <tr>
            <td class="w-1/2 align-top px-2.5">
                Prepared By
    
                <div class="text-center mt-10">
                    (SGD)
                </div>
    
                <div class="font-bold mt-6">
                    {{ $user }}
                </div>
    
                <div class="mt-1">
                    Accounting Staff
                </div>

                <div class="">
                    &nbsp;
                </div>
    
                <div class="mt-3.5">
                    Date: {{ now('Asia/Manila')->format('n/j/Y') }}
                </div>
            </td>
    
            <td class="w-1/2 align-top px-2.5">
                Certified Correct
    
                <div class="text-center mt-10">
                    (SGD)
                </div>
    
                <div class="font-bold mt-6">
                    {{ $signatoryName }}, {{ $authofficialcourse }}
                </div>

                <div class="mt-1">
                    {{ $signatoryPosition }}
                </div>
    
                <div class="">
                    Authorized Official
                </div>
    
                <div class="mt-3.5">
                    Date: {{ now('Asia/Manila')->format('n/j/Y') }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Generated By Footer Tag (Asia/Manila Time) -->
    <div class="mt-8 text-center italic text-xs text-gray-500">
        Generated: {{ now('Asia/Manila')->format('Y-m-d h:i A') }} &bull; This is a computer-generated statement. No signature required.
    </div>

    <pre>&nbsp;</pre>
</body>
</html>