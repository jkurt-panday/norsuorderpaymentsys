# Graduate Ledger Normalization Plan

## Context

The `graduate_ledgers` table currently stores `student_name`, `course`, `school_year`, `semester_short`, and `semester` as raw strings on every transaction row. This causes data-integrity problems: the same student can be split across multiple "different" records due to name typos or formatting differences (e.g. "ABABON, LAHLAINE A." vs "Ababon, Lahlaine A."), balances can't be reliably summed, and reporting by course/term requires fragile string matching.

Goal: split the flat table into `students`, `courses`, `academic_terms`, and a slimmer `graduate_ledgers` transaction table that references them via foreign keys, while keeping the frontend (Inertia/React) changes minimal.

Stack: Laravel + Inertia + React (TypeScript), MySQL/Postgres via Eloquent, `maatwebsite/excel` for import, `barryvdh/laravel-dompdf` for PDF statements.

---

## 1. Database changes

### New table: `students`

```php
Schema::create('students', function (Blueprint $table) {
    $table->id();
    $table->string('student_number')->nullable()->unique();
    $table->string('last_name');
    $table->string('first_name');
    $table->string('middle_name')->nullable();
    $table->string('raw_name_from_csv')->nullable(); // original "Last, First M." string, for import matching/audit
    $table->timestamps();

    $table->index(['last_name', 'first_name']);
});
```

### New table: `courses`

```php
Schema::create('courses', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique(); // e.g. "MA English", "MBA" — matches existing courseOptions list in frontend
    $table->string('title')->nullable();
    $table->timestamps();
});
```

Seed with the existing hardcoded `courseOptions` array currently duplicated in `AddTransaction.tsx` and `EditTransaction.tsx`:

```
PhD Educational Management, PhD Mathematics Education, EdD Educational Management,
EdD Instruction, EdD Science Education, EdD Filipino, EdD Technology Management,
DM HRM, DM Public Administration, MBA, MPH, MA Education, MA English, MA Filipino,
MA History, MA Psychology, MA Mathematics, MAECE, MS Agriculture, MSIT, MTE,
MPM HRM, MPM LGA
```

### New table: `academic_terms`

```php
Schema::create('academic_terms', function (Blueprint $table) {
    $table->id();
    $table->string('school_year', 20);       // e.g. "2025-2026"
    $table->string('semester_short', 20);    // e.g. "1st Sem."
    $table->string('semester', 50);          // e.g. "First Semester"
    $table->unsignedTinyInteger('sort_order'); // 1 = 1st Sem, 2 = 2nd Sem, 3 = Summer — for chronological ordering
    $table->timestamps();

    $table->unique(['school_year', 'semester_short']);
});
```

Seed pattern per school year: `1st Sem. / First Semester (sort_order 1)`, `2nd Sem. / Second Semester (sort_order 2)`, `Summer / Summer (sort_order 3)` — matches the existing `semesterOptions` mapping logic in the frontend forms.

### Modify: `graduate_ledgers`

Replace `student_name`, `course`, `school_year`, `semester_short`, `semester` with foreign keys:

```php
Schema::table('graduate_ledgers', function (Blueprint $table) {
    $table->foreignId('student_id')->after('id')->constrained()->cascadeOnDelete();
    $table->foreignId('course_id')->nullable()->after('student_id')->constrained();
    $table->foreignId('academic_term_id')->nullable()->after('course_id')->constrained();

    $table->dropColumn(['student_name', 'course', 'school_year', 'semester_short', 'semester']);
});
```

Also normalize the transaction-type field. Current `ar_payment` string column holds inconsistent values (`AR`, `Payment`, `P`, `PAYMENR` [typo], `Adjustment`, `ADJ`, `SETTLED`, and parentheses-as-negative amounts). Replace with:

```php
$table->enum('entry_type', ['ar', 'payment', 'adjustment'])->nullable()->after('particulars');
// then drop the old ar_payment column
```

Keep `units`, `transaction_date`, `reference_or_jev_number`, `particulars`, `tuition_per_unit_or_misc`, `amount`, `remarks`, `input_by` as-is — these are already legitimately transaction-scoped fields, no change needed. (`input_by` can stay a plain string unless staff have real login accounts worth foreign-keying to `users`.)

### Resulting `graduate_ledgers` shape

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| student_id | FK → students | required |
| course_id | FK → courses | nullable |
| academic_term_id | FK → academic_terms | nullable |
| units | decimal(4,1) | nullable |
| transaction_date | date | nullable |
| reference_or_jev_number | string | nullable |
| particulars | string | nullable |
| tuition_per_unit_or_misc | decimal(10,2) | default 0.00 |
| entry_type | enum('ar','payment','adjustment') | nullable |
| amount | decimal(10,2) | default 0.00 |
| remarks | string | nullable |
| input_by | string | nullable |
| timestamps | | |

### Data migration note

Existing rows in `graduate_ledgers` must be backfilled: for each distinct `student_name`, create-or-match a `Student` (store original string in `raw_name_from_csv`); for each distinct `course`, create-or-match a `Course`; for each distinct `school_year` + `semester_short` pair, create-or-match an `AcademicTerm`. Then update each ledger row with the resolved `student_id` / `course_id` / `academic_term_id` before dropping the old string columns. This should be a one-off data migration script run before the schema-drop migration, not done live in the app.

---

## 2. Eloquent models

### `app/Models/Student.php`
```php
class Student extends Model
{
    protected $fillable = ['student_number', 'last_name', 'first_name', 'middle_name', 'raw_name_from_csv'];

    public function graduateLedgers()
    {
        return $this->hasMany(GraduateLedger::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->last_name}, {$this->first_name} " . ($this->middle_name ? substr($this->middle_name, 0, 1) . '.' : ''));
    }

    public function balance(): float
    {
        return $this->graduateLedgers()
            ->selectRaw("SUM(CASE WHEN entry_type = 'ar' THEN amount WHEN entry_type = 'payment' THEN -amount ELSE 0 END) as bal")
            ->value('bal') ?? 0.0;
    }
}
```

### `app/Models/Course.php`
```php
class Course extends Model
{
    protected $fillable = ['code', 'title'];

    public function graduateLedgers()
    {
        return $this->hasMany(GraduateLedger::class);
    }
}
```

### `app/Models/AcademicTerm.php`
```php
class AcademicTerm extends Model
{
    protected $fillable = ['school_year', 'semester_short', 'semester', 'sort_order'];

    public function graduateLedgers()
    {
        return $this->hasMany(GraduateLedger::class);
    }
}
```

### `app/Models/GraduateLedger.php`
Add relationships:
```php
public function student() { return $this->belongsTo(Student::class); }
public function course() { return $this->belongsTo(Course::class); }
public function academicTerm() { return $this->belongsTo(AcademicTerm::class); }
```

---

## 3. Backend controller changes (`GraduateLedgerController.php`)

### `index()`
- Search: change `where('student_name', 'like', ...)` to `whereHas('student', fn($q) => $q->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"))`. Same pattern for course and reference number search.
- Filters: `school_year`/`semester` filters become `whereHas('academicTerm', ...)`; `course` filter becomes `whereHas('course', fn($q) => $q->where('code', $course))`.
- `totalStudents` stat: change `distinct('student_name')->count('student_name')` to `distinct('student_id')->count('student_id')` (this also fixes a latent overcount bug from name-typo duplicates).
- `totalAssessments`/`totalPayments`: replace the `UPPER(TRIM(ar_payment))` raw-SQL matching with `where('entry_type', 'ar')` / `where('entry_type', 'payment')`.

### `create()` / `edit()`
- Replace `GraduateLedger::distinct()->pluck('student_name')` with `Student::orderBy('last_name')->get(['id','last_name','first_name','middle_name'])`.
- Prop passed to Inertia changes from `studentNames: string[]` to `students: {id, last_name, first_name, middle_name}[]`.
- `edit()` must eager-load relations: `GraduateLedger::with(['student','course','academicTerm'])->findOrFail($id)`, and the `record` payload sent to Inertia must include `student_id`, `course_id`, `academic_term_id` (not the old string fields).

### `store()` / `update()`
- Validation changes: `student_id` (required, exists:students,id) replaces `student_name`; `course_id` (nullable, exists:courses,id) replaces `course`; `academic_term_id` (nullable, exists:academic_terms,id) replaces `school_year`/`semester_short`/`semester`; `entry_type` (nullable, in:ar,payment,adjustment) replaces `ar_payment`.
- If adding an "inline new student" flow: accept optional `new_student` object (last_name, first_name, middle_name) and run `Student::firstOrCreate(...)` before saving the ledger row when `student_id` isn't provided.
- Auto-compute-amount logic (units × rate when entry_type is 'ar' and amount blank) stays unchanged, just keyed off `entry_type === 'ar'` instead of `ar_payment === 'AR'`.

### `import()` / `mapImportRow()`
- This is the most involved change. For each imported row:
  1. Resolve or create the `Student` (match on `raw_name_from_csv` first; fall back to last/first name match; create if not found).
  2. Resolve or create the `Course` (match on `code`).
  3. Resolve or create the `AcademicTerm` (match on `school_year` + `semester_short`).
  4. Build the ledger insert row using the resolved IDs instead of raw strings.
- For performance on large imports, pre-pass the file once to collect all distinct student/course/term values, bulk `firstOrCreate` (or `upsert`) them first to build in-memory ID maps, then do the chunked `GraduateLedger::insert()` using those maps — avoids one query per row.
- Keep the existing `normalizeArPaymentType()` logic (it already handles `PAYMENR` typo, `ADJ`, `SETTLED`, parentheses-negative, etc.) — just have it return `'ar' | 'payment' | 'adjustment'` and write to `entry_type`.

### `printSelect()` / `generatePdf()`
- Change from `where('student_name', $selectedStudent)` (string match) to `where('student_id', $selectedStudentId)`. This is safer than the current approach, since it removes the risk of a name-typo causing the wrong student's records to be pulled.
- The `students` list passed to the picker should become `{id, full_name}[]` instead of `string[]`.

### `transformRecord()`
- Change `$r->student_name` → `$r->student->full_name`, `$r->course` → `$r->course?->code`, `$r->school_year` → `$r->academicTerm?->school_year`, `$r->semester_short` → `$r->academicTerm?->semester_short`, `$r->ar_payment` → map `$r->entry_type` back to display label ('AR'/'Payment'/'Adjustment').
- Output shape (`LedgerRecord` interface on the frontend) stays identical — this is the seam that keeps the `Index.tsx` page unchanged.
- Eager-load relations in `index()` (`->with(['student','course','academicTerm'])`) before calling `transformRecord()` to avoid N+1 queries.

### `getFilterOptions()`
- Replace `GraduateLedger::distinct()->pluck('course')` with `Course::orderBy('code')->pluck('code')`.
- Replace `GraduateLedger::distinct()->pluck('school_year')` with `AcademicTerm::distinct()->orderBy('school_year','desc')->pluck('school_year')`.
- Replace `GraduateLedger::distinct()->pluck('semester_short')` with `AcademicTerm::distinct()->pluck('semester_short')`.

---

## 4. Frontend changes

### `graduate-ledger/Index.tsx` — **no changes needed**
This page only consumes the already-transformed `LedgerRecord` shape from `transformRecord()`. As long as the backend keeps outputting the same `name`/`course`/`schoolYear`/`semester`/`arPayment` fields, this file is untouched.

### `graduate-ledger/AddTransaction.tsx` and `graduate-ledger/EditTransaction.tsx`
These two files are near-duplicates; recommend extracting a shared `<StudentCoursePicker />` component used by both, since the change is identical in each.

Change needed:
- Replace the free-text `<Input list="student-names-list">` + `<datalist>` pattern with a proper `<select>` bound to `student_id`, populated from a `students: {id, last_name, first_name, middle_name}[]` prop (was `studentNames: string[]`).
- Add an "+ Add New Student" option/button that reveals a small inline sub-form (last name, first name, middle name) and submits a `new_student` object alongside the transaction on save, OR posts to a separate "create student" endpoint first and then selects the returned ID.
- `course` select: keep the same hardcoded `courseOptions` list, but bind to `course_id` (looked up against the seeded `courses` table) instead of the raw label — either resolve label→id client-side using a `courses: {id, code}[]` prop, or keep submitting the code string and resolve it server-side in `store()`/`update()`.
- `semester_short`/`semester` selects: keep the same UX (select semester short, auto-fill full label), but bind to `academic_term_id` — resolved server-side from `school_year` + `semester_short`, or via a `academic_terms: {id, school_year, semester_short}[]` prop if you want it fully client-resolved.
- `ar_payment` select: rename to `entry_type`, values become `'ar' | 'payment' | 'adjustment'` (lowercase) to match the new enum. Display labels in the `<option>` tags can stay capitalized ("AR", "Payment", "Adjustment") for the UI.
- `EditTransaction.tsx` specifically: the `record` prop shape changes from flat strings (`student_name`, `course`, `school_year`, `semester_short`, `semester`, `ar_payment`) to IDs (`student_id`, `course_id`, `academic_term_id`, `entry_type`) so the form can correctly pre-select the picker values.

### `graduate-ledger/PrintSelect.tsx` (not yet reviewed, but referenced in controller)
- `students` prop changes from `string[]` to `{id, full_name}[]`.
- The "selected student" URL param changes from a name string to a numeric `student_id`.

---

## 5. Suggested order of implementation

1. Write and run the new-table migrations (`students`, `courses`, `academic_terms`) — additive, no risk to existing app.
2. Seed `courses` and `academic_terms` from the hardcoded frontend option lists.
3. Write and run a one-off data-backfill script that populates `students`/`courses`/`academic_terms` from existing `graduate_ledgers` rows and fills new `student_id`/`course_id`/`academic_term_id`/`entry_type` columns (added as nullable alongside the old string columns first — don't drop the old columns yet).
4. Update models, then update controller methods one at a time (`transformRecord()` and `getFilterOptions()` first, since `index()` is highest-traffic and easiest to verify), testing against the still-present old columns as a fallback.
5. Update `AddTransaction.tsx` / `EditTransaction.tsx` with the new picker component.
6. Verify search, filters, import, PDF export, and balance calculations all work against the new columns.
7. Only after full verification: drop the old string columns (`student_name`, `course`, `school_year`, `semester_short`, `semester`, `ar_payment`) from `graduate_ledgers` in a final migration.
