<!DOCTYPE html>
<html>
    <head>
        @php
            // ── Variable & Logic Extraction ──
            $normalizeText = static fn ($value) => str_replace(['−', '–', '—'], '-', (string) ($value ?? ''));
            $firstRecord   = $records->first();
            $cleanAmount   = static fn ($val) => abs((float) preg_replace('/[^\d.]/', '', (string) ($val ?? 0)));

            // Universal property extractor (works on objects AND arrays)
            $getProp = static function ($obj, array $keys) {
                if (!$obj) return null;
                foreach ($keys as $key) {
                    if (is_object($obj) && isset($obj->{$key}) && $obj->{$key} !== '') {
                        $value = $obj->{$key};
                        return (is_scalar($value) || $value === null) ? $value : null;
                    }
                    if (is_array($obj) && isset($obj[$key]) && $obj[$key] !== '') {
                        $value = $obj[$key];
                        return (is_scalar($value) || $value === null) ? $value : null;
                    }
                }
                return null;
            };

            // ── Student number: pull from law_student relationship ──
            $studentNumber = null;
            if ($firstRecord) {
                $lawStudent = $getProp($firstRecord, ['law_student', 'lawStudent']);
                $studentNumber = $getProp($lawStudent, ['student_number', 'studentNumber', 'student_no']);
            }
            $studentNumber = $studentNumber ?? ($assessment->student_id ?? '—');

            // ── Course description ──
            $courseDesc = '—';
            if ($firstRecord) {
                $lawCourse = $getProp($firstRecord, ['law_course', 'lawCourse', 'course']);
                $courseDesc = $getProp($lawCourse, ['course_desc', 'courseDesc']) ?? '—';
            }

            // ── Course code ──
            $courseCode = '—';
            if ($firstRecord) {
                $lawCourse = $getProp($firstRecord, ['law_course', 'lawCourse', 'course']);
                $courseCode = $getProp($lawCourse, ['course_code', 'courseCode', 'code']) ?? '—';
            }

            // ── Academic term (school year + semester) ──
            $schoolYear    = '—';
            $semesterLabel = '—';
            if ($firstRecord) {
                $term = $getProp($firstRecord, ['law_academic_term', 'lawAcademicTerm', 'academic_term', 'academicTerm']);
                $schoolYear    = $getProp($term, ['school_year', 'schoolYear']) ?? '—';
                $semesterLabel = $getProp($term, ['semester', 'semesterOrSummer', 'semester_or_summer']) ?? '—';
            }

            // ── Collect unique terms across all records, then sort ──
            $semesterOrder = [
                'First Semester'  => 1,
                'Second Semester' => 2,
                'Summer'          => 3,
            ];
            
            $schoolYears = [];
            $semesters   = [];
            
            foreach ($records as $r) {
                $term = $getProp($r, ['law_academic_term', 'lawAcademicTerm', 'academic_term', 'academicTerm']);
            
                $sy  = $getProp($term, ['school_year', 'schoolYear']);
                $sem = $getProp($term, ['semester', 'semesterOrSummer', 'semester_or_summer']);
            
                if ($sy  && !in_array($sy,  $schoolYears, true)) $schoolYears[] = $sy;
                if ($sem && !in_array($sem, $semesters,   true)) $semesters[]   = $sem;
            }
            
            // Sort semesters in academic order
            usort($semesters, fn ($a, $b) => ($semesterOrder[$a] ?? 99) <=> ($semesterOrder[$b] ?? 99));
            sort($schoolYears);
            
            $schoolYear    = $schoolYears ? implode('<br>', array_map('e', $schoolYears)) : '—';
            $semesterLabel = $semesters   ? implode('<br>', array_map('e', $semesters))   : '—';

            // ── Units, form number, student name ──
            $units         = $firstRecord ? ($getProp($firstRecord, ['units']) ?? '—') : '—';
            $studentId     = $studentNumber; // use the resolved student number above
            $formNumber    = $assessment->reference_number ?? ($assessment->id ?? '—');
            $studentName   = $studentName ?? (is_object($student ?? null) ? ($student->full_name ?? ($student->name ?? '—')) : '—');

            // ── Payment determination logic ──
            $isPayment = static function ($record) use ($getProp) {
                $rawType = strtoupper(trim((string) ($getProp($record, ['arOrPayment', 'ar_or_payment', 'arPayment', 'entry_type']) ?? '')));
                return in_array($rawType, ['PAYMENT', 'P', 'PAYMENR', 'SETTLED', 'ADJUSTMENT', 'ADJ'])
                    || str_contains($rawType, 'ADJUST')
                    || str_contains($rawType, 'PAY');
            };

            // ── Amount formatting logic ──
            $formatAmount = static function ($record) use ($cleanAmount, $isPayment, $getProp) {
                $amount = $cleanAmount($getProp($record, ['amount']) ?? 0);
                if ($isPayment($record)) {
                    return '- ' . number_format($amount, 2);
                }
                return number_format($amount, 2);
            };

            // ── Header image ──
            $headerImagePath = resource_path('views/pdf/norsu header.png');
            $headerImageBase64 = file_exists($headerImagePath)
                ? base64_encode(file_get_contents($headerImagePath))
                : ($logoDataUri ?? null);

            // ── Signatory & user metadata ──
            $user              = $preparedBy ?? '—';
            $official          = activeAuthorizedOfficial();
            $signatoryName     = $official?->name ?? 'Maurice Anaver B. Dordado, CPA';
            $authofficialcourse = $official?->course ?? 'CPA';
            $signatoryPosition = $official?->position ?? 'Head of Accounting/Division/Unit';
        @endphp

        <meta charset="utf-8">
        <title>Statement of Account - {{ $studentName }}</title>
        @vite(['resources/css/app.css'])
    </head>
<body class="text-[11px] w-full min-w-[800px] text-gray-900 font-sans">

    @if($headerImageBase64)
        <div class="flex justify-center mb-2">
            <img
                class="w-full max-w-[605px]"
                src="{{ str_starts_with($headerImageBase64, 'data:') ? $headerImageBase64 : 'data:image/png;base64,' . $headerImageBase64 }}"
                alt="NORSU Header"
            >
        </div>
    @endif

    {{-- <pre>{{ json_encode(get_defined_vars(), JSON_PRETTY_PRINT) }}</pre> --}}
    
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
            <td class="italic align-top py-0.5">{!! $semesterLabel !!}</td>
        </tr>
        <tr>
            <td class="font-bold align-top py-0.5">Course:</td>
            <td class="italic align-top py-0.5">{{ $normalizeText($courseDesc) }}</td>
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
                <th class="text-left px-1.5 py-1 w-[15%]">Date</th>
                <th class="text-left px-1.5 py-1 w-[14%]">Ref #</th>
                <th class="text-left px-1.5 py-1 w-[24%]">Particulars</th>
                <th class="text-left px-1.5 py-1 w-[16%]">Type</th>
                <th class="text-left px-1.5 py-1 w-[16%]">Sem</th>
                <th class="text-right px-1.5 py-1 w-[24%]">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $r)
                @php
                    $txDate  = $getProp($r, ['transaction_date', 'transactionDate']);
                    $refNo   = $getProp($r, ['reference_number', 'referenceNo', 'reference_jev_or_number']) ?? '';
                    $part    = $getProp($r, ['particulars']) ?? '—';
                    $rawType = (string) ($getProp($r, ['entry_type', 'arOrPayment', 'ar_or_payment', 'arPayment', 'type']) ?? '');
                    $isAdj   = str_contains(strtoupper($rawType), 'ADJUST');
                    $isPay   = $isPayment($r);
                    $type = strtoupper(
                        $isAdj ? $rawType : ($isPay ? 'Payment' : ($rawType ?: 'Charge'))
                    );

                    // Semester for this row (fall back to the header semester)
                    $rowTerm = $getProp($r, ['law_academic_term', 'lawAcademicTerm', 'academic_term', 'academicTerm']);
                    $rowSem  = $getProp($rowTerm, ['semester']) ?? $semesterLabel;
                @endphp
                <tr class="border-b">
                    <td class="px-1.5 py-1">{{ $normalizeText($txDate ? \Carbon\Carbon::parse($txDate)->format('m/d/Y') : '—') }}</td>
                    <td class="px-1.5 py-1">{{ $normalizeText($refNo) }}</td>
                    <td class="px-1.5 py-1">{{ $normalizeText($part) }}</td>
                    <td class="px-1.5 py-1">{{ $type }}</td>
                    <td class="px-1.5 py-1">{{ $normalizeText($rowSem) }}</td>
                    <td class="text-right px-1.5 py-1">{{ $formatAmount($r) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="italic text-gray-500 px-1.5 py-3">No matching ledger records found.</td>
                </tr>
            @endforelse

            <tr class="border-t-2 border-black font-bold">
                <td colspan="5" class="text-right px-1.5 py-1.5">Outstanding Balance</td>
                <td class="text-right px-1.5 py-1.5">{{ number_format($summary['outstandingBalance'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Dual Signatory Section -->
    <table class="w-full mt-20 text-lg">
        <tr>
            <td class="w-1/2 align-top px-2.5">
                Prepared By

                <div class="text-left mt-10">
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

                <div class="text-left mt-10">
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