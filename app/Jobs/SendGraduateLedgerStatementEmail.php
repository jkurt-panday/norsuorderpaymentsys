<?php

namespace App\Jobs;

use App\Mail\GraduateLedgerStatementMail;
use App\Models\GraduateLedger;
use App\Models\Student;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class SendGraduateLedgerStatementEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 360;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $studentId, public ?string $schoolYear = null, public ?string $semester = null, public ?string $subject = null, public ?string $note = null) {}

    public function handle(): void
    {
        $startedAt = microtime(true);
        $student = Student::findOrFail($this->studentId);
        if (! filled($student->email)) {
            return;
        }

        $records = GraduateLedger::with(['student', 'course', 'academicTerm'])
            ->where('student_id', $student->id)
            ->when($this->schoolYear, fn ($q, $v) => $q->whereHas('academicTerm', fn ($t) => $t->where('school_year', $v)))
            ->when($this->semester, fn ($q, $v) => $q->whereHas('academicTerm', fn ($t) => $t->where('semester', $v)))
            ->orderBy('id')->get();
        $summary = ['totalCharges' => (float) $records->where('entry_type', 'ar')->sum('amount'), 'totalPayments' => (float) $records->where('entry_type', 'payment')->sum('amount'), 'totalAdjustments' => (float) $records->where('entry_type', 'adjustment')->sum('amount')];
        $summary['outstandingBalance'] = $summary['totalCharges'] - $summary['totalPayments'] - $summary['totalAdjustments'];

        try {
            $pdf = Pdf::view('pdf.student-ledger-statement', ['student' => $student, 'studentName' => $student->full_name, 'records' => $records, 'summary' => $summary, 'generatedAt' => now()->format('Y-m-d')])
                ->driver('browsershot')
                ->withBrowsershot(fn (Browsershot $b) => $b->newHeadless()->timeout(300)->setOption('protocolTimeout', 300_000))
                ->format('a4')->generatePdfContent();
            Mail::to($student->email)->send(new GraduateLedgerStatementMail($student, $pdf, $this->subject, $this->note));
            Log::info('Graduate Ledger statement email sent', ['student_id' => $student->id, 'record_count' => $records->count(), 'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000)]);
        } catch (Throwable $exception) {
            Log::error('Graduate Ledger statement email failed', ['student_id' => $student->id, 'record_count' => $records->count(), 'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000), 'exception' => $exception::class, 'message' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Graduate Ledger statement job exhausted retries', ['student_id' => $this->studentId, 'exception' => $exception?->getMessage()]);
    }
}
