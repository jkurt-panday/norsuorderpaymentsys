# Financial Precision Fix: Technical Architecture & Implementation Summary

This document summarizes the changes implemented on branch `financial-precision-fix` to resolve floating-point arithmetic errors in student ledger calculations.

---

## 1. Executive Summary & Root Cause

### The Problem
* **Database storage was already correct:** Columns such as `amount`, `rate`, and `discount_amount` in `graduate_ledgers` and `law_school_ledgers` are typed as `DECIMAL(12, 2)` or `DECIMAL(10, 2)`.
* **Application layer precision loss:** When aggregating ledger records in PHP (e.g. `calculateStudentBalanceNormalized`), amounts were converted to native IEEE-754 binary floating-point numbers (`(float)`). Repeated additions and subtractions (`$totalCharges += $amount`) introduced fractional binary representation errors (e.g. `0.10 + 0.20 = 0.30000000000000004`).
* **Inconsistent normalization:** `cleanAmount()` routines used `preg_replace` and raw float casting, stripping signs or returning imprecise float representations.

### The Solution Strategy
1. **Preserve exact decimal arithmetic** during summation using PHP's `BCMath` extension (`bcadd`, `bcsub` at scale 2).
2. **Centralize financial calculations** in a dedicated, stateless service (`BalanceCalculator`).
3. **Normalize inputs** to 2 decimal places using `cleanAmount()` while maintaining float signatures expected by existing controllers, frontend TypeScript interfaces, and PHPUnit assertions.
4. **Maintain contract compatibility:** APIs and controller responses continue returning `float` numbers (e.g. `totalCharges: 9500.0`), preventing any breaking changes in Inertia React components or Blade templates.

---

## 2. File-by-File Summary of Changes

### A. Core Arithmetic Service
#### `app/Services/BalanceCalculator.php` (New File)
* **Scale:** Sets `self::SCALE = 2` for two decimal places (Philippine Peso).
* **Methods:**
  * `add(string $a, string $b): string`: Performs `bcadd($a, $b, 2)`.
  * `subtract(string $a, string $b): string`: Performs `bcsub($a, $b, 2)`.
  * `summarize(iterable $records): array`:
    * Sums `ar` entries into `$totalCharges` using `bcadd`.
    * Sums `adjustment` entries into `$totalAdjustments` using `bcadd`.
    * Sums payments into `$totalPayments` using `bcadd`.
    * Computes `outstandingBalance = bcsub(bcsub($totalCharges, $totalPayments), $totalAdjustments)`.
    * Casts results to `float` for contract compatibility.
  * `summarizeLaw(iterable $records): array`:
    * Specialized ledger aggregation for Law School (handles legacy `ar_or_payment` flags).

---

### B. Controller Normalization & Calculation Paths
#### `app/Http/Controllers/GraduateLedgerController.php`
* **`cleanAmount(mixed $rawAmount): float`**:
  * Strips formula indicators (`=`), Excel error tokens (`#VALUE!`, `#REF!`, etc.).
  * Filters non-numeric characters using `preg_replace('/[^\d.]/', '', $str)`.
  * Clamps amounts to PostgreSQL/MySQL `DECIMAL(12, 2)` limits (< `100,000,000.00`).
  * Normalizes to 2 decimal places via `round((float) $cleaned, 2)`.
* **`calculateStudentBalanceNormalized(int $studentId, int $termId): array`**:
  * Delegates record summation directly to `BalanceCalculator::summarize($records)`.

#### `app/Http/Controllers/LawSchoolLedgerController.php`
* **`cleanAmount(mixed $rawAmount): float`**:
  * Matches Graduate ledger cleanup while preserving negative signs (`[^\d.\-]`).
  * Clamps within `[-99999999.99, 99999999.99]`.
  * Returns `round((float) $cleaned, 2)`.
* **`calculateStudentBalanceNormalized(int $studentId, int $termId): array`**:
  * Delegates record summation directly to `BalanceCalculator::summarizeLaw($records)`.

---

### C. PDF / Print Statements
#### `resources/views/pdf/student-ledger-statement.blade.php`
#### `resources/views/pdf/law-student-ledger-statement.blade.php`
#### `resources/views/pdf/assessment-soa.blade.php`
* Replaced ad-hoc `$cleanAmount` closures with consistent float casting and 2-decimal formatting:
  ```php
  $cleanAmount = static fn ($val) => abs((float) preg_replace('/[^\d.]/', '', (string) ($val ?? 0)));
  ```
* Standardized statement default summary blocks:
  ```php
  $summary = $ledgerStatement['summary'] ?? [
      'totalCharges' => 0.0,
      'totalPayments' => 0.0,
      'outstandingBalance' => 0.0,
  ];
  ```

---

### D. Automated Tests & Static Analysis
#### `tests/Unit/BalanceCalculatorTest.php` (New File)
Covers:
1. Exact 2-decimal addition and subtraction.
2. Handling floating-point drift (e.g. adding `0.10` ten times yields exactly `1.00`, avoiding `0.9999999999999999`).
3. Handling mixed charges, payments, and adjustments.
4. Law ledger calculations and legacy payment labels.
5. Empty record collections defaulting to `0.0`.

#### Static Analysis & Linting:
* **PHPStan:** Analyzes `app/Services/BalanceCalculator.php` with 0 errors.
* **Laravel Pint:** Code style formatted and verified clean.
* **Feature Tests:** `LawSchoolLedgerTest` passes with 4/4 tests, 41 assertions.

---

## 3. Data Flow Diagram

```
User Input / CSV Import / Excel
             │
             ▼
   cleanAmount($raw) ────► Normalized float (2 decimal places)
             │
             ▼
   DECIMAL(12, 2) Column (Database Storage)
             │
             ▼
   Balance Calculation Request
             │
             ▼
   BalanceCalculator::summarize() / summarizeLaw()
     ├── Convert each amount: number_format(abs($val), 2, '.', '')
     ├── Accumulate with BCMath: bcadd($total, $amount, 2)
     └── Outstanding: bcsub($charges, $payments, 2)
             │
             ▼
   Return float summary:
   [ 'totalCharges' => 1000.0, 'totalPayments' => 500.0, 'outstandingBalance' => 500.0 ]
             │
             ├──────────────────────────┐
             ▼                          ▼
   Inertia JSON to React UI        Blade PDF Views
   (Types match `number`)          (Formatted with number_format)
```

---

## 4. Key Decisions & Rationale

| Decision | Why It Was Chosen |
|---|---|
| **Use BCMath internally instead of float `+=`** | Binary IEEE-754 floats accumulate rounding errors across large batches of transactions. BCMath calculates in base-10 strings, guaranteeing 100% precision. |
| **Return `float` from `BalanceCalculator::summarize()`** | Maintains strict backwards compatibility with existing Inertia React frontend components (`summary.totalCharges: number`) and PHPUnit tests asserting JSON values (`->assertJsonPath('summary.totalCharges', 9500)`). |
| **Retain `cleanAmount(): float` in controllers** | Prevents breaking type signatures in existing callers, models, and export mappers. |
| **Keep `DECIMAL` columns in database unchanged** | The schema was already well-designed (`DECIMAL(12,2)`); the issue was strictly application-layer math drift. |
