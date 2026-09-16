# Cashier → Ledger Hardening — What Changed

**Date:** 2026-09-15  
**Scope:** Public OP submission wiring + cashier auto-post safety fixes  
**Migrations required:** None (uses existing `form_inputs.student_num`, `form_inputs.academic_term` columns)

---

## Files Modified

| # | File | What changed |
|---|------|-------------|
| 1 | `app/Models/FormInput.php` | Fillable + relations |
| 2 | `app/Http/Requests/PublicFormSubmissionRequest.php` | Nullable rules for student fields |
| 3 | `app/Http/Controllers/FormInputController.php` | Dropdown props + resolve-then-store |
| 4 | `app/Services/CashierLedgerPostingService.php` | Full rewrite (5 fixes) |
| 5 | `app/Http/Controllers/CashierRequestController.php` | New toast suffix |
| 6 | `tests/Feature/GraduateLedgerImportTest.php` | Two new test cases |

---

## 1. `app/Models/FormInput.php`

**Why:** `student_num` and `academic_term` columns exist in the DB (migration `2026_09_09_000002`) but were not in `$fillable` and had no Eloquent relationships. The cashier service needed `$formInput->student` and `$formInput->academicTerm` to be accessible.

**What changed:**
- Added `student_num` and `academic_term` to the `$fillable` array.
- Added `student()`: `belongsTo(Student::class, 'student_num')` — explicit foreign key because the column is named `student_num`, not `student_id`.
- Added `academicTerm()`: `belongsTo(AcademicTerm::class, 'academic_term')` — same reasoning.

**Break risk:** Near zero. Adding fillable keys and relations doesn't affect existing `create()` calls. Old rows with `null` in those columns return `null` from the new relations.

---

## 2. `app/Http/Requests/PublicFormSubmissionRequest.php`

**Why:** The frontend already sends `student_num`, `school_year`, and `semester` on every OP submission, but `validated()` silently dropped them because there was no rule. The controller couldn't access them.

**What changed:**
- Added three nullable rules:
  - `student_num` → `nullable|string|max:50`
  - `school_year` → `nullable|string|max:20`
  - `semester` → `nullable|string|max:50`

**Break risk:** Zero. All three are `nullable`, so existing General-tab submissions (which send empty strings) pass validation as before.

---

## 3. `app/Http/Controllers/FormInputController.php`

### 3a. `create()` — dropdown data

**Why:** `SubmitForm.tsx` already declared `courses?` and `academicTerms?` as optional props, but `create()` never passed them. The Student-tab dropdowns rendered empty.

**What changed:**
- Added two queries: `Course` (id, course_desc) and `AcademicTerm` (id, school_year, semester).
- Passed both as props to the Inertia render.

**Break risk:** None. The React props were already optional with `[]` defaults.

### 3b. `store()` — resolve-then-store

**Why:** `form_inputs.student_num` is a **foreign key to `students.id`** (integer), not the 9-digit student ID number. Writing the raw digits directly would either crash (FK violation) or silently link to the wrong student.

**What changed:**
- New `resolveStudentId(string $rawStudentNum): ?int`: takes the 9-digit string, tries exact raw match on `students.student_number`, then falls back to a digit-normalized `REPLACE()` query (handles `2026-00123` vs `202600123`). Returns the integer `students.id` or `null`.
- New `resolveAcademicTermId(string $schoolYear, string $semester): ?int`: looks up `academic_terms` by exact `school_year` + `semester` match. Returns the integer `academic_terms.id` or `null`.
- Both resolved values are written to `FormInput::create()` alongside the existing fields. On any lookup failure, `null` is stored — the submission always succeeds.

**Break risk:** Low. Two extra SELECTs per submission. Old OP rows keep `null` and work exactly as before through the fallback path.

---

## 4. `app/Services/CashierLedgerPostingService.php` (full rewrite)

This file had five problems fixed in one pass.

### 4a. FK-first student resolution

**Before:** Only matched by name (`LOWER(first_name)` + `LOWER(last_name)`).  
**After:** If `form_inputs.student_num` is populated, `Student::find()` does an exact integer lookup — no ambiguity. Name fallback only runs when the FK is null.

### 4b. Gating the name fallback

**Before:** Any payer name matching exactly one student auto-posted — including office/agencies on the General tab.  
**After:** Name fallback only runs when `looksLikeStudentSubmission()` is true:
- `form_inputs.student_num` is set, **or**
- `form_inputs.academic_term` is set, **or**
- `membership.member_code` contains "student" (case-insensitive)

General-tab payers (e.g. offices) will never accidentally post to a student's ledger.

### 4c. Correction-as-update (the double-credit fix)

**Before:** When a cashier corrected an already-paid OP (changed OR number/date), the service inserted a **second** full-amount payment row → double credit.  
**After:** The service tags each payment row with `remarks = "OR from OP {reference_number}"`. On re-save, it finds the existing row by that remark tag and **updates it in place** (new OR, new date, corrected amount). The student never gets double-credited.

**Guard:** Before updating, a collision check verifies the new OR number isn't already used by a *different* ledger row. If it is, the service returns `or_already_used` instead of clobbering.

### 4d. OP-term-first priority

**Before:** Always reused the student's latest ledger row for course + term, which could be last year's.  
**After:** If the OP itself carries an `academic_term`, that is used first. Falls back to the student's latest ledger term only when the OP has none. Course is resolved from a ledger row inside the resolved term when possible, then falls back to latest overall.

### 4e. Stable remark tag

**Why:** The correction-as-update logic needs a deterministic way to find "the payment row for this specific OP." The `remarks` field already stored `"OR from OP {ref}"` and is never touched again by other services, making it a reliable anchor.

---

## 5. `app/Http/Controllers/CashierRequestController.php`

**Why:** The new `or_already_used` reason from the rewrite needed a human-readable toast message.

**What changed:** Added `'or_already_used' => ' Could not update ledger: that OR number is already in use.'` to the `ledgerPostingSuffix()` match.

**Break risk:** None. Only fires for the new reason code.

---

## 6. `tests/Feature/GraduateLedgerImportTest.php`

**Why:** Two new behaviors needed test coverage: FK resolution on OP form submission, and correction-as-update.

### New tests:

| Test | Verifies |
|------|----------|
| `test_public_op_form_submission_resolves_student_num_and_academic_term_to_fks` | Submitting `student_num = '202600999'` (unhyphenated) and `school_year + semester` stores the correct **integer FK** in `form_inputs`, not the raw digits. |
| `test_cashier_payment_correction_updates_existing_ledger_row_in_place` | First save creates a payment row; second save (different OR) updates that **same row** instead of inserting a second one. Old OR disappears, new OR appears, total rows = 1. |

### Existing tests (unchanged, still valid):

| Test | Why it still passes |
|------|---------------------|
| `test_cashier_payment_auto_posts...` | Uses `membership.member_code = 'STUDENT'` → passes `looksLikeStudentSubmission()` → name fallback matches. |
| `test_cashier_payment_is_idempotent...` | Second save has same OR → finds existing row → enters correction path → updates in place → count stays 1. |
| `test_cashier_payment_does_not_post...` | Membership = 'OTHER', no student signals → `looksLikeStudentSubmission()` returns false → `student_not_found` → no ledger row created. |
| All existing cash-mask / assertDatabaseHas tests | Untouched files. |

---

## Outstanding / future work

| Item | Why it's deferred | When to revisit |
|------|-------------------|-----------------|
| Double-submit race | Concurrent requests can both pass the `exists()` check. | When you can add a unique composite index (requires migration). |
| Student data collection from imported rows | Imported students have no `student_number`. | Next iteration — the FK resolution code is ready, just needs a collection mechanism. |
| Law vs. Graduate crossover | The service only posts to `GraduateLedger`. | When law ledger posting is needed — add a school scope to the resolution. |
