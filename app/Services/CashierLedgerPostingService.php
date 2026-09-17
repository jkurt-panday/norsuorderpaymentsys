<?php

namespace App\Services;

use App\Models\FormInput;
use App\Models\GraduateLedger;
use App\Models\StaffInput;
use App\Models\Student;
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
 * - Student resolution priority: direct FK link (form_inputs.student_num)
 *   → digit-normalized student_number lookup → gated exact name match.
 * - Name fallback only runs for student-like submissions, never for
 *   general/office payers.
 * - Term priority: the OP's own academic_term → the student's latest
 *   ledger term. Course always comes from ledger history.
 */
class CashierLedgerPostingService
{
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
                // Guard: don't let a correction steal an OR number that
                // belongs to a *different* ledger row.
                $collision = GraduateLedger::query()
                    ->where('reference_number', $orNo)
                    ->where('entry_type', 'payment')
                    ->whereKeyNot($existing->id)
                    ->exists();

                if ($collision) {
                    return ['posted' => false, 'reason' => 'or_already_used'];
                }

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

            // Fresh path: idempotency on the OR number itself.
            $alreadyPosted = GraduateLedger::query()
                ->where('reference_number', $orNo)
                ->where('entry_type', 'payment')
                ->exists();

            if ($alreadyPosted) {
                return ['posted' => false, 'reason' => 'already_posted'];
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

            return ['posted' => false, 'reason' => 'insert_failed'];
        }

        return ['posted' => true, 'reason' => null];
    }

    /**
     * Stable tag linking a ledger payment row back to its source OP,
     * so corrections can find and update it instead of duplicating.
     */
    private function opRemark(FormInput $formInput): ?string
    {
        return $formInput->reference_number
            ? "OR from OP {$formInput->reference_number}"
            : null;
    }

    /**
     * Resolve the course + term for the payment row.
     * Term: the OP's own academic_term first, then the student's latest
     * ledger term. Course: the student's latest ledger course, preferring
     * a row inside the resolved term when one exists.
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
        $courseId = GraduateLedger::query()
            ->where('student_id', $student->id)
            ->where('academic_term_id', $termId)
            ->latest('id')
            ->value('course_id')
            ?? GraduateLedger::query()
                ->where('student_id', $student->id)
                ->latest('id')
                ->value('course_id');

        if ($courseId === null) {
            return null;
        }

        return ['course_id' => (int) $courseId, 'academic_term_id' => (int) $termId];
    }

    /**
     * Student resolution priority:
     * 1. Direct FK link (form_inputs.student_num → students.id).
     * 2. Gated exact name match — only for student-like submissions.
     */
    private function resolveStudent(FormInput $formInput): ?Student
    {
        if ($formInput->student_num) {
            $linked = Student::query()->find($formInput->student_num);
            if ($linked !== null) {
                return $linked;
            }
        }

        if (! $this->looksLikeStudentSubmission($formInput)) {
            return null;
        }

        return $this->resolveStudentByName(
            $formInput->firstname_or_office,
            $formInput->lastname_or_agency,
        );
    }

    /**
     * Only allow the (less reliable) name fallback when the OP carries
     * student signals — a linked student, an academic term, or a
     * student-flavoured membership. General/office payers never match.
     */
    private function looksLikeStudentSubmission(FormInput $formInput): bool
    {
        if ($formInput->student_num || $formInput->academic_term) {
            return true;
        }

        $code = strtolower((string) ($formInput->membership?->member_code ?? ''));
        $desc = strtolower((string) ($formInput->membership?->member_desc ?? ''));

        return str_contains($code, 'student')
            || str_contains($desc, 'student');
    }

    /**
     * Match the payer name on an Order of Payment against Students.
     * Form fields mirror student parts: firstname_or_office → first name,
     * lastname_or_agency → last name. Only an exact single match counts.
     */
    private function resolveStudentByName(?string $firstName, ?string $lastName): ?Student
    {
        $first = Str::of((string) $firstName)->squish()->toString();
        $last = Str::of((string) $lastName)->squish()->toString();

        if ($first === '' && $last === '') {
            return null;
        }

        $candidates = Student::query()
            ->when($last !== '', fn ($q) => $q->whereRaw('LOWER(last_name) = ?', [mb_strtolower($last)]))
            ->when($first !== '', fn ($q) => $q->whereRaw('LOWER(first_name) = ?', [mb_strtolower($first)]))
            ->get(['id', 'student_number', 'last_name', 'first_name', 'middle_name']);

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        return null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
