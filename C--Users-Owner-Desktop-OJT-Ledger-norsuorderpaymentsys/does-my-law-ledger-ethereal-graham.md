# Plan: Implement Membership Scholarship (NAPU / NORSUFFA) in Graduate Ledger

## Context
In the Law School Ledger, students can receive a Latin Honor Scholarship (Summa / Magna Cum Laude) applied to their AR transactions. For the **Graduate School Ledger**, faculty and staff who are members of **NAPU** (NORSU Administrative Personnel Union) or **NORSUFFA** (NORSU Federated Faculty Association) are eligible for a **100% Membership Scholarship** applied to their Assessment (AR) transactions.

---

## Requirements
1. **Discount Rate**: 100% full scholarship discount for both **NAPU** and **NORSUFFA**.
2. **Target Entry**: Applicable only to `AR` (Assessment) entries in `GraduateLedger`.
3. **Transaction Handling**:
   - Updates the original AR record: sets `membership` to selected type (`NAPU` or `NORSUFFA`), updates `discount_amount`, sets remaining amount to `0.00`, and marks status appropriately.
   - Automatically creates an `adjustment` ledger row:
     - `particulars`: `"NAPU Membership Scholarship (100%)"` or `"NORSUFFA Membership Scholarship (100%)"`
     - `reference_number`: `"MEMBERSHIP-NAP-{id}"` or `"MEMBERSHIP-NOR-{id}"`
     - `amount`: full discount amount
     - `remarks`: `"NAPU membership discount applied to AR #{id}"` (or NORSUFFA)
     - `input_by`: logged-in user ID
4. **Interactive UI**:
   - Clicking an eligible AR row (or action button) opens the **Apply Membership Scholarship Dialog**.
   - Displays student name, original assessment amount, and options to select **NAPU** or **NORSUFFA**.
   - Displays a preview of the discount (100% discount, full amount deducted).
   - Shows `(NAPU)` or `(NORSUFFA)` badge on AR rows that have the discount applied.

---

## Key Files to Create / Modify

### 1. Database Migration
- **Create**: `database/migrations/xxxx_xx_xx_xxxxxx_add_membership_to_graduate_ledgers_table.php`
  - Adds columns:
    - `membership`: `string('membership', 20)->nullable()->after('status')`
    - `discount_amount`: `decimal('discount_amount', 12, 2)->default(0)->after('membership')`

### 2. Eloquent Model
- **Modify**: `app/Models/GraduateLedger.php`
  - Add `'membership'` and `'discount_amount'` to `$fillable`.
  - Add `'discount_amount' => 'decimal:2'` to `$casts`.

### 3. Backend Controller & Routes
- **Modify**: `app/Http/Controllers/GraduateLedgerController.php`
  - Add method `applyMembership(Request $request, int $id): RedirectResponse`:
    - Validates `membership` in `['NAPU', 'NORSUFFA']`.
    - Verifies `$record->entry_type === 'ar'`.
    - Computes 100% discount amount.
    - Executes database transaction updating the AR record and creating the adjustment record.
  - Update `transformRecord(GraduateLedger $r)`:
    - Include `'membership' => $r->membership` in the returned data array.
- **Modify**: `routes/web.php`
  - Add route inside `graduate-ledger` route group:
    ```php
    Route::post('/{id}/apply-membership', [GraduateLedgerController::class, 'applyMembership'])->name('apply-membership');
    ```

### 4. Frontend Components
- **Modify**: `resources/js/pages/graduate-ledger/Index.tsx`
  - Update `LedgerRecord` interface with `membership?: string | null`.
  - Add state for dialog: `membershipTarget`, `selectedMembership`, `isApplyingMembership`.
  - Add `applyMembershipDiscount` handler invoking `router.post(`/graduate-ledger/${membershipTarget.id}/apply-membership`, ...)`.
  - Update table AR badges to be interactive when `!r.membership` and show badge with `(NAPU)` or `(NORSUFFA)` when applied.
  - Add `<Dialog>` component matching the design with cards for:
    - **NAPU**: NORSU Administrative Personnel Union (100% scholarship)
    - **NORSUFFA**: NORSU Federated Faculty Association (100% scholarship)

---

## Verification & Testing Plan
1. **Migration Test**: Run `php artisan migrate` to verify the schema update.
2. **Functional Test**:
   - Navigate to Graduate Ledger (`/graduate-ledger`).
   - Find an `AR` entry with an assessment amount (e.g. ₱5,000.00).
   - Click the AR badge to open the Apply Membership Scholarship dialog.
   - Select **NAPU** or **NORSUFFA** and submit.
   - Verify that:
     1. The AR amount is reduced to ₱0.00.
     2. An adjustment transaction is created with particulars `"NAPU Membership Scholarship (100%)"` or `"NORSUFFA Membership Scholarship (100%)"`.
     3. Total assessments, adjustments, and outstanding balance statistics recalculate accurately.
     4. Statement of Account / PDF generation correctly reflects the adjustment.
