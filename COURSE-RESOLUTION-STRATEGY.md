# Student Course Resolution Strategy (Middle Ground)

**Date:** 2026-09-15  
**Topic:** Resolving student course on Order of Payment (OP) submissions & Cashier Ledger Posting  
**Migration Requirement:** **None** (operates entirely within the existing schema)

---

## 1. Problem Overview

- Every row in `graduate_ledgers` requires a non-null `course_id`.
- The public OP form (`SubmitForm.tsx`) has a `Course` combobox, but `form_inputs` does not store `course_id`.
- Forcing students to select a course manually can lead to accidental mismatches (e.g., an MBA student selecting MSIT).

---

## 2. The Middle-Ground Strategy: "Ledger-Derived Course"

Instead of storing and relying on student-selected courses from the public OP form, the system derives the course directly from official student ledger records.

```
[Public OP Submission]
  └─ Student enters Student ID (e.g. 202500001) + Term (e.g. 2024-2025 2nd Sem)

[Cashier Payment Save]
  └─ Resolve Student by ID / Name
       └─ Query Student's Latest Ledger Record:
            1. Try latest course in the resolved Academic Term
            2. Fallback to latest course overall
                 │
                 ├─► Found?  ──► Auto-post Payment row with resolved course_id.
                 │
                 └─► None?   ──► Mark OP as "Paid", toast notification to staff:
                                 "No prior ledger context. Create initial assessment first."
```

---

## 3. How Different Student Scenarios Are Handled

| Scenario | What Happens | Result |
|---|---|---|
| **Enrolled Student (95%+ of cases)** | Student submits OP with Student ID. Cashier records OR. | Course auto-derived from prior ledger record. Auto-posts to ledger instantly. |
| **Continuing Student Paying Previous Term** | Student selects previous term on OP form. | System looks for ledger history in that term first, preserving historical course accuracy. |
| **Brand-New Student (No Ledger History)** | Student pays entrance/comprehensive exam or initial tuition before assessment is created. | OP is successfully marked `Paid` with OR details. Cashier gets a notice that no ledger row was created. Staff creates initial assessment in `/graduate-ledger`. |

---

## 4. Key Benefits

1. **Zero Database Migrations:** Uses existing `students`, `form_inputs`, and `graduate_ledgers` schema.
2. **Prevents Student User Errors:** Students cannot mistakenly post a payment under the wrong degree program.
3. **Simpler Public Form:** The course field on the public OP form can be optional or removed, reducing friction for students.
4. **Maintains Single Source of Truth:** Academic programs remain governed by the registrar/accounting ledger records.

---

## 5. Recommended Codebase Adjustments

- [ ] **`resources/js/pages/public/SubmitForm.tsx`**: Make the `Course` combobox optional (remove `required` attribute) or hide it when Student ID is entered.
- [ ] **`app/Http/Controllers/CashierRequestController.php`**: Ensure the toast message for `no_ledger_context` clearly informs the cashier:
  - *"Status updated to Paid. (No prior ledger records found for this student; payment not auto-posted)."*
