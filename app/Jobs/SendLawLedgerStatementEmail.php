<?php

namespace App\Jobs;

use App\Mail\LawSchoolLedgerStatementMail;
use App\Models\AcademicTerm;
use App\Models\ActivityLog;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class SendLawLedgerStatementEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 360;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $studentId,
        public ?string $schoolYear = null,
        public ?string $semester = null,
        public ?string $subject = null,
        public ?string $note = null,
        public ?string $examPeriod = null,
        public ?string $examDeadline = null,
    ) {}

    public function handle(): void
    {
        $student = Student::query()->findOrFail($this->studentId);

        if (! filled($student->email)) {
            return;
        }

        $semester = $this->normalizeStatementSemester($this->semester);

        $records = LawSchoolLedger::query()
            ->with(['lawStudent', 'lawCourse', 'lawAcademicTerm'])
            ->where('student_id', $student->id)
            ->when(
                $this->schoolYear,
                fn ($query, $schoolYear) => $query->whereHas(
                    'lawAcademicTerm',
                    fn ($termQuery) => $termQuery->where('school_year', $schoolYear),
                ),
            )
            ->orderBy('id', 'asc')
            ->get()
            ->when(
                $semester,
                fn ($records, $selectedSemester) => $records->filter(
                    fn (LawSchoolLedger $record): bool => AcademicTerm::normalizeSemester(
                        (string) $record->semester_or_summer,
                    ) === $selectedSemester,
                )->values(),
            );

        $studentName = trim("{$student->last_name}, {$student->first_name} ".($student->middle_name ? substr($student->middle_name, 0, 1).'.' : ''));
        $summary = $this->calculateStudentBalanceNormalized($records);

        $pdfContent = Pdf::view('pdf.law-student-ledger-statement', [
            'student' => $student,
            'studentName' => $studentName,
            'records' => $records,
            'summary' => $summary,
            'semesterLabel' => $semester ?? 'All Terms',
            'generatedAt' => now()->timezone('Asia/Manila')->format('Y-m-d h:i A'),
        ])
            ->driver('browsershot')
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $this->configureBrowsershot($browsershot);
            })
            ->format('a4')
            ->generatePdfContent();

        Mail::to($student->email)->send(
            new LawSchoolLedgerStatementMail(
                $student,
                $pdfContent,
                $this->subject,
                $this->note,
                $this->examPeriod,
                $this->examDeadline,
            )
        );

        $this->recordActivityLog($student, $records->count(), $semester);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Failed to send Law Ledger statement email', [
            'student_id' => $this->studentId,
            'school_year' => $this->schoolYear,
            'semester' => $this->semester,
            'message' => $exception?->getMessage(),
        ]);
    }

    private function recordActivityLog(Student $student, int $recordCount, ?string $semester): void
    {
        ActivityLog::create([
            'actor_id' => null,
            'actor_name' => 'Queue Worker',
            'actor_role' => 'system',
            'action' => 'email.sent',
            'target_id' => null,
            'subject_type' => LawSchoolLedger::class,
            'subject_id' => null,
            'description' => 'Law Ledger SOA emailed to '.$student->full_name.' ('.$student->email.').',
            'meta' => [
                'to' => $student->email,
                'student_id' => $student->id,
                'student_number' => $student->student_number,
                'student_name' => $student->full_name,
                'school_year' => $this->schoolYear,
                'semester' => $semester,
                'subject' => $this->subject,
                'exam_period' => $this->examPeriod,
                'exam_deadline' => $this->examDeadline,
                'record_count' => $recordCount,
                'queued_job' => true,
            ],
        ]);
    }

    /**
     * Applies the shared Browsershot hardening used by Chrome-rendered statements.
     */
    private function configureBrowsershot(Browsershot $browsershot): void
    {
        $browsershot
            ->newHeadless()
            ->timeout(300)
            ->setOption('protocolTimeout', 300_000);
    }

    /** @param iterable<LawSchoolLedger> $records */
    private function calculateStudentBalanceNormalized(iterable $records): array
    {
        $totalCharges = 0.0;
        $totalPayments = 0.0;

        foreach ($records as $record) {
            $entryType = strtolower((string) ($record->entry_type ?? ''));
            $amount = $this->cleanAmount($record->amount ?? 0);

            if ($entryType === 'ar') {
                $totalCharges += abs($amount);
            } elseif (in_array($entryType, ['payment', 'adjustment'], true)) {
                $totalPayments += abs($amount);
            } else {
                $label = strtoupper(trim((string) ($record->ar_or_payment ?? '')));
                if ($label === 'AR' || $label === 'ASSESSMENT') {
                    $totalCharges += abs($amount);
                } else {
                    $totalPayments += abs($amount);
                }
            }
        }

        return [
            'totalCharges' => $totalCharges,
            'totalPayments' => $totalPayments,
            'outstandingBalance' => $totalCharges - $totalPayments,
        ];
    }

    private function cleanAmount(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    private function normalizeStatementSemester(?string $semester): ?string
    {
        if (! filled($semester)) {
            return null;
        }

        return AcademicTerm::normalizeSemester((string) $semester)
            ?? match ($this->normalizeSemesterDisplay((string) $semester)) {
                '1st Sem' => 'First Semester',
                '2nd Sem' => 'Second Semester',
                'Summer' => 'Summer',
                default => null,
            };
    }

    private function normalizeSemesterDisplay(string $value): string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', ' ', trim($value)));

        return match ($normalized) {
            '1ST SEM', 'FIRST SEMESTER', '1ST SEMESTER', 'FIRST SEM', '1ST', '1' => '1st Sem',
            '2ND SEM', 'SECOND SEMESTER', '2ND SEMESTER', 'SECOND SEM', '2ND', '2' => '2nd Sem',
            'SUMMER', 'SUMMER TERM', 'SUMMER SEMESTER', '3RD SEM', '3RD SEMESTER' => 'Summer',
            default => trim($value),
        };
    }
}
