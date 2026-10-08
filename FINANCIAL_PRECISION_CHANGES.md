# Financial Precision Fix: Architecture & Implementation Summary

This document summarizes the changes introduced to eliminate floating-point drift in ledger financial calculations while preserving database schemas, response contracts, and UI compatibility.

---

## 1. Executive Summary

- **Problem:** Database columns correctly use `DECIMAL(12,2)`, but controller and view layers performed binary floating-point calculations (`(float)`, `+=`, `-=`), leading to IEEE 754 precision drift (e.g., `0.10 + 0.20 = 0.30000000000000004`).
- **Solution:** 
  1. Standardized input normalization in `cleanAmount()` to return normalized 2-decimal values.
  2. Created a dedicated `BalanceCalculator` service utilizing PHP's `BCMath` extension for exact scale-2 arithmetic.
  3. Routed ledger balance calculations through `BalanceCalculator`.
  4. Updated Blade templates and exports to consume the standardized numbers.
  5. Added unit tests for exact arithmetic and regression-tested feature suites.

---

## 2. File-by-File Changes

### `app/Services/BalanceCalculator.php` (New)
- **Role:** Centralized, stateless financial calculator for student ledgers.
- **Implementation:**
  - Uses `bcadd` and `bcsub` with scale `2`.
  - Accepts typed `numeric-string` arguments for core math operations.
  - `summarize(iterable $records)`: Computes `totalCharges` (AR), `totalPayments`, `totalAdjustments`, and net `outstandingBalance`.
  - `summarizeLaw(iterable $records)`: Specialization for law school ledgers handling legacy `ar_or_payment` labels and no separate adjustment column.
  - Returns `array{totalCharges: float, totalPayments: float, totalAdjustments?: float, outstandingBalance: float}` to preserve backward compatibility with JSON serializers, Inertia page props, and Blade templates.

### `app/Http/Controllers/GraduateLedgerController.php`
- **`cleanAmount(mixed $rawAmount): float`**:
  - Sanitizes spreadsheet input errors (`#VALUE!`, `#REF!`, `#DIV/0!`, `#NAME?`, `#N/A`, etc.).
  - Strips stray formula prefixes (`=`).
  - Cleans non-numeric noise with regex while preserving signs and decimals.
  - Clamps to PostgreSQL `DECIMAL(12,2)` max boundaries (`-99999999.99` to `99999999.99`).
  - Formats cleanly to 2 decimals and returns `round((float) $cleaned, 2)`.
- **`calculateStudentBalanceNormalized(...)`**:
  - Replaced inline float accumulation loops with `BalanceCalculator::summarize($records)`.

### `app/Http/Controllers/LawSchoolLedgerController.php`
- **`cleanAmount(mixed $rawAmount): float`**:
  - Aligned with the same sanitized, clamped, 2-decimal float return as GraduateLedger.
- **`calculateStudentBalanceNormalized(...)`**:
  - Replaced inline loops with `BalanceCalculator::summarizeLaw($records)`.

### PDF Blade Templates
- `resources/views/pdf/student-ledger-statement.blade.php`
- `resources/views/pdf/law-student-ledger-statement.blade.php`
- `resources/views/pdf/assessment-soa.blade.php`
- **Changes:**
  - Replaced ad-hoc regex closures with a simplified `$cleanAmount` helper casting amounts to float.
  - Preserved standard `number_format($val, 2, '.', '')` calls for PDF rendering.

### `tests/Unit/BalanceCalculatorTest.php` (New)
- Verifies `add` and `subtract` on decimal values.
- Verifies floating-point drift elimination: 10 additions of `0.10` equals exactly `1.00`, avoiding `0.9999999999999999`.
- Verifies charge/payment/adjustment categorization and empty record sets.
- Verifies law school legacy label fallbacks.

### `tests/Feature/GraduateLedgerTest.php`
- Fixed missing `particulars` test fixtures in `test_index_filters_records_by_student_and_academic_term_balance_status` and `test_remarks_are_calculated_as_outstanding_or_settled_per_student_and_academic_term` (required by non-null DB column).
- Corrected export test index assertion corresponding to `inputByDisplay`.

---

## 3. Data Flow Architecture

```
[User Input / CSV / Excel / Manual Entry]
                 │
                 ▼
     `cleanAmount($raw)` ──► Sanitizes symbols/formulas, clamps to 12,2, rounds to 2 dp
                 │
                 ▼
       Database Storage ────► PostgreSQL / SQLite `DECIMAL(12, 2)` (Exact)
                 │
                 ▼
        Eloquent Query ─────► Hydrates records
                 │
                 ▼
  `BalanceCalculator::summarize()` ──► `number_format(abs($amount), 2, '.', '')` (Exact string)
                 │                     `bcadd()` / `bcsub()` (Scale 2, Zero Drift)
                 ▼
   `[charges, payments, balance]` ───► Cast to float at boundary for compatibility
                 │
   ┌─────────────┴─────────────┐
   ▼                           ▼
Inertia / JSON API      Blade PDF Views (rendered via `number_format`)
```

---

## 4. Key Review Considerations for Second Opinion

1. **Precision Boundary:**
   - Calculations within `BalanceCalculator` run inside BCMath string arithmetic (`scale = 2`), preventing compounding intermediate errors.
   - Outputs at the API boundary are cast to `float` to avoid breaking frontend TypeScript types (`number`) and JSON schema expectations.
2. **Backward Compatibility:**
   - No database schema migrations required (already `DECIMAL(12,2)`).
   - No breaking changes to existing controller response shapes.
3. **Edge Case Handling:**
   - Spreadsheet formula artifacts (`=SUM(...)`, `#REF!`, `#VALUE!`) default safely to `0.00`.
   - Out-of-bounds numbers are clamped to column capacity rather than triggering database overflow exceptions.
