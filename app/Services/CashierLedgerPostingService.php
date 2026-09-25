<?php

namespace App\Services;

use App\Models\FormInput;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\StaffInput;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Posts a graduate ledger payment row when the cashier saves an OR
 * on an Order of Payment, whenever the payer can be matched to a
 * ledger student.
 *
 * Rules:
 * - Only run when status is 'paid' and the OR number exists.
 * - Corrections (re-saves of an already-paid OP) UPDATE the existing
 *   ledger row for that OP instead of inserting a duplicate.
 * - Students are resolved only through the explicit form_inputs.student_num
 *   link selected by staff. Cashier-side number/name guessing is prohibited.
 * - Term priority: the OP's own academic_term → the student's latest
 *   ledger term. Course priority: the OP's course, then ledger history.
 */
class CashierLedgerPostingService
{
    /**
     * Reverse the ledger payment for a cancelled paid OP.
     *
     * The original payment is retained for audit history. A negative payment
     * offsets it, and the stable reversal remark makes repeated cancellation
     * attempts idempotent.
     */
    public function reversePayment(StaffInput $staffInput): void
    {
        $staffInput->loadMissing('formInput.course');

        $formInput = $staffInput->formInput;
        $college = $formInput?->course?->course_college;

        if (! in_array($college, ['Graduate School', 'School of Law'], true)) {
            return;
        }

        $ledgerModel = $college === 'Graduate School'
            ? GraduateLedger::class
            : LawSchoolLedger::class;
        $opRemark = $this->opRemark($formInput);
        $reversalRemark = $opRemark.':REVERSAL';

        $original = $ledgerModel::query()
            ->where('remarks', $opRemark)
            ->where('entry_type', 'payment')
            ->lockForUpdate()
            ->first();

        if ($original === null) {
            throw new \RuntimeException('The original ledger payment could not be found.');
        }

        $ledgerModel::query()->updateOrCreate(
            [
                'remarks' => $reversalRemark,
                'entry_type' => 'payment',
            ],
            [
                'student_id' => $original->student_id,
                'course_id' => $original->course_id,
                'academic_term_id' => $original->academic_term_id,
                'transaction_date' => now()->format('Y-m-d'),
                'reference_number' => $staffInput->or_no,
                'particulars' => Str::limit('Reversal of '.$original->particulars, 255, ''),
                'rate' => '0.00',
                'amount' => -abs((float) $original->amount),
                'status' => 'posted',
                'input_by' => auth()->id(),
            ],
        );
    }

    /**
     * Route a payment to exactly one ledger using the OP's selected course.
     * Course-less, General, and Undergraduate OPs require no ledger posting.
     *
     * @return array{posted: bool, reason: string|null, ledger: string|null}
     */
    public function postPayment(StaffInput $staffInput): array
    {
        $staffInput->loadMissing('formInput.course', 'formInput.membership');

        $formInput = $staffInput->formInput;
        if ($formInput === null) {
            return ['posted' => false, 'reason' => 'no_form', 'ledger' => null];
        }

        $college = $formInput->course?->course_college;

        if (! in_array($college, ['Graduate School', 'School of Law'], true)) {
            return ['posted' => false, 'reason' => 'not_required', 'ledger' => null];
        }

        $student = $this->resolveStudent($formInput);
        if ($student === null) {
            return [
                'posted' => false,
                'reason' => 'student_not_found',
                'ledger' => $college === 'Graduate School' ? 'graduate' : 'law',
            ];
        }

        $result = match ($college) {
            'Graduate School' => $this->postGraduatePayment($staffInput),
            'School of Law' => $this->postLawPayment($staffInput),
        };

        return [
            ...$result,
            'ledger' => match ($college) {
                'Graduate School' => 'graduate',
                'School of Law' => 'law',
            },
        ];
    }

    /**
     * @return array{posted: bool, reason: string|null}
     */
    public function postGraduatePayment(StaffInput $staffInput): array
    {
        $staffInput->loadMissing('formInput.membership');

        $orNo = trim((string) ($staffInput->or_no ?? ''));
        if ($orNo === '' || $staffInput->status !== 'paid') {
            return ['posted' => false, 'reason' => 'missing_or'];
        }

        $formInput = $staffInput->formInput;
        if ($formInput === null) {
            return ['posted' => false, 'reason' => 'no_form'];
        }

        $student = $this->resolveStudent($formInput);
        if ($student === null) {
            return ['posted' => false, 'reason' => 'student_not_found'];
        }

        $context = $this->resolveLedgerContext($formInput, $student);
        if ($context === null) {
            return ['posted' => false, 'reason' => 'no_ledger_context'];
        }

        $opRemark = $this->opRemark($formInput);
        $orDate = $this->normalizeDate($staffInput->or_date) ?? now()->format('Y-m-d');
        $amount = abs((float) $formInput->amount);

        $particulars = $formInput->paymentDetailOption?->payment_desc
            ?? $formInput->membership?->member_desc
            ?? 'Payment';

        try {
            // Correction path: a ledger payment already exists for this OP.
            // Update it in place so re-saves never double-credit the student.
            $existing = ($opRemark !== null)
                ? GraduateLedger::query()
                    ->where('remarks', $opRemark)
                    ->where('entry_type', 'payment')
                    ->first()
                : null;

            if ($existing !== null) {
                $existing->update([
                    'student_id' => $student->id,
                    'course_id' => $context['course_id'],
                    'academic_term_id' => $context['academic_term_id'],
                    'transaction_date' => $orDate,
                    'reference_number' => $orNo,
                    'amount' => $amount,
                ]);

                return ['posted' => true, 'reason' => null];
            }

            GraduateLedger::create([
                'student_id' => $student->id,
                'course_id' => $context['course_id'],
                'academic_term_id' => $context['academic_term_id'],
                'entry_type' => 'payment',
                'transaction_date' => $orDate,
                'reference_number' => $orNo,
                'particulars' => Str::limit($particulars ?: 'Payment', 255, ''),
                'rate' => '0.00',
                'amount' => $amount,
                'remarks' => $opRemark,
                'status' => 'posted',
                'input_by' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Cashier ledger posting failed', [
                'staff_input_id' => $staffInput->id,
                'or_no' => $orNo,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return ['posted' => true, 'reason' => null];
    }

    /**
     * Post a law school ledger payment row when the cashier saves an OR
     * on an Order of Payment, whenever the payer can be matched to a
     * law student.
     *
     * Rules mirror postGraduatePayment:
     * - Only run when status is 'paid' and the OR number exists.
     * - Corrections (re-saves of an already-paid OP) UPDATE the existing
     *   ledger row for that OP instead of inserting a duplicate.
     * - Student resolution priority: direct FK link (form_inputs.student_num)
     *   → digit-normalized student_number lookup → gated exact name match.
     * - Name fallback only runs for student-like submissions, never for
     *   general/office payers.
     * - Term priority: the OP's own academic_term → the student's latest
     *   ledger term. Course always comes from ledger history.
     *
     * @return array{posted: bool, reason: string|null}
     */
    public function postLawPayment(StaffInput $staffInput): array
    {
        $staffInput->loadMissing('formInput.membership');

        $orNo = trim((string) ($staffInput->or_no ?? ''));
        if ($orNo === '' || $staffInput->status !== 'paid') {
            return ['posted' => false, 'reason' => 'missing_or'];
        }

        $formInput = $staffInput->formInput;
        if ($formInput === null) {
            return ['posted' => false, 'reason' => 'no_form'];
        }

        $student = $this->resolveLawStudent($formInput);
        if ($student === null) {
            return ['posted' => false, 'reason' => 'student_not_found'];
        }

        $context = $this->resolveLawLedgerContext($formInput, $student);
        if ($context === null) {
            return ['posted' => false, 'reason' => 'no_ledger_context'];
        }

        $opRemark = $this->opRemark($formInput);
        $orDate = $this->normalizeDate($staffInput->or_date) ?? now()->format('Y-m-d');
        $amount = abs((float) $formInput->amount);

        $particulars = $formInput->paymentDetailOption?->payment_desc
            ?? $formInput->membership?->member_desc
            ?? 'Payment';

        try {
            // Correction path: a ledger payment already exists for this OP.
            $existing = ($opRemark !== null)
                ? LawSchoolLedger::query()
                    ->where('remarks', $opRemark)
                    ->where('entry_type', 'payment')
                    ->first()
                : null;

            if ($existing !== null) {
                $existing->update([
                    'student_id' => $student->id,
                    'course_id' => $context['course_id'],
                    'academic_term_id' => $context['academic_term_id'],
                    'transaction_date' => $orDate,
                    'reference_number' => $orNo,
                    'amount' => $amount,
                ]);

                return ['posted' => true, 'reason' => null];
            }

            LawSchoolLedger::create([
                'student_id' => $student->id,
                'course_id' => $context['course_id'],
                'academic_term_id' => $context['academic_term_id'],
                'entry_type' => 'payment',
                'transaction_date' => $orDate,
                'reference_number' => $orNo,
                'particulars' => Str::limit($particulars ?: 'Payment', 255, ''),
                'rate' => '0.00',
                'amount' => $amount,
                'remarks' => $opRemark,
                'status' => 'posted',
                'input_by' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Law school ledger posting failed', [
                'staff_input_id' => $staffInput->id,
                'or_no' => $orNo,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return ['posted' => true, 'reason' => null];
    }

    /**
     * Resolve the course + term for a law school payment row.
     * Term: the OP's own academic_term first, then the student's latest
     * ledger term. Course: the OP's selected course first, then ledger history.
     *
     * @return array{course_id: int, academic_term_id: int}|null
     */
    private function resolveLawLedgerContext(FormInput $formInput, Student $student): ?array
    {
        $termId = $formInput->academic_term;

        if ($termId === null) {
            $termId = LawSchoolLedger::query()
                ->where('student_id', $student->id)
                ->latest('id')
                ->value('academic_term_id');
        }

        if ($termId === null) {
            return null;
        }

        $courseId = $formInput->course_id;

        if ($courseId === null) {
            $courseId = LawSchoolLedger::query()
                ->where('student_id', $student->id)
                ->where('academic_term_id', $termId)
                ->latest('id')
                ->value('course_id')
                ?? LawSchoolLedger::query()
                    ->where('student_id', $student->id)
                    ->latest('id')
                    ->value('course_id');
        }

        if ($courseId === null) {
            return null;
        }

        return ['course_id' => (int) $courseId, 'academic_term_id' => (int) $termId];
    }

    /** Resolve the law student only through the explicit staff-created link. */
    private function resolveLawStudent(FormInput $formInput): ?Student
    {
        return $this->resolveStudent($formInput);
    }

    /**
     * Resolve the course + term for the payment row.
     * Term: the OP's own academic_term first, then the student's latest
     * ledger term. Course: the OP's selected course first, then ledger history.
     *
     * @return array{course_id: int, academic_term_id: int}|null
     */
    private function resolveLedgerContext(FormInput $formInput, Student $student): ?array
    {
        $termId = $formInput->academic_term;

        if ($termId === null) {
            $termId = GraduateLedger::query()
                ->where('student_id', $student->id)
                ->latest('id')
                ->value('academic_term_id');
        }

        if ($termId === null) {
            return null;
        }

        // Prefer a course from a row inside the resolved term; fall back
        // to the student's latest course overall.
        $courseId = $formInput->course_id;

        if ($courseId === null) {
            $courseId = GraduateLedger::query()
                ->where('student_id', $student->id)
                ->where('academic_term_id', $termId)
                ->latest('id')
                ->value('course_id')
                ?? GraduateLedger::query()
                    ->where('student_id', $student->id)
                    ->latest('id')
                    ->value('course_id');
        }

        if ($courseId === null) {
            return null;
        }

        return ['course_id' => (int) $courseId, 'academic_term_id' => (int) $termId];
    }

    /**
     * Resolve only the student explicitly linked by staff.
     *
     * Name-based fallback is intentionally prohibited so cashier processing
     * cannot silently post a payment to the wrong ledger.
     */
    private function resolveStudent(FormInput $formInput): ?Student
    {
        if (! $formInput->student_num) {
            return null;
        }

        return Student::query()->find($formInput->student_num);
    }

    /** Build the stable marker used to identify the ledger row for one OP. */
    private function opRemark(FormInput $formInput): string
    {
        return 'OP:'.$formInput->getKey();
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
