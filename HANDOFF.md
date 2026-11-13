# HANDOFF — Graduate & Law School Ledger System
_Updated: Fri Nov 13 2026_

## Stack
Laravel 11 · Inertia React · MySQL locally · PhpSpreadsheet · Pest · Browsershot

## Core Data
- `students` is shared by current Graduate and Law ledgers.
- `graduate_ledgers` and `law_school_ledgers` are separate transaction tables.
- `courses.course_college`: `Graduate School` or `School of Law`.
- `academic_terms` is shared.
- Entry types: `ar`, `payment`, `adjustment`.

## Import Pipeline
Both importers use two passes: collect distinct students/courses/terms, bulk-resolve
lookups, then map and insert ledger rows in 1,000-row chunks. Unknown values use
school-specific course and shared term fallbacks; blank-name rows are skipped.
There is no deduplication or `import_report` behavior.

## Graduate Import
- `GraduateLedgerController::buildImportLookupMaps` bulk-creates reference data.
- Student number/email enrichment uses one CASE update per field, not N queries.
- New student IDs are re-fetched by imported last names, not a full-table reload.
- CSV and Excel pass two use one transaction per import with chunked inserts.
- Excel keeps `setReadDataOnly(false)` and per-cell `getCalculatedCellValue` for
  rate/amount formulas; amount behavior was not changed.
- Graduate course spelling is preserved except case-only variants share the
  case-insensitive unique course code.
- Course bulk creation uses `insertOrIgnore`, preventing real workbooks from
  failing when MySQL treats variants such as `ph.D.` and `PH.D.` as duplicates.
- Graduate import UI posts the selected `File` directly with `router.post` and
  the generated Wayfinder `importMethod` route. This fixes the prior immediate
  `useForm.setData()` + `post()` race that could submit `file: null`; validation
  errors now show an error toast.
- The actual legacy Graduate workbook labels its name column `FF`; this is now
  recognized as a full-name header. Previously all 40,624 rows were classified
  as blank because another recognized header put parsing into mapped mode.

## Verification
- Current Graduate import suite: 35 tests, 206 assertions passed.
- Formula rate/amount test: 1 test, 4 assertions passed with normal exit.
- Combined suite reports all tests passed but exits 1 because of project-level
  PHPUnit issue settings; no failed test is listed.
- Old `GraduateLedgerImportRequirementsTest` expects removed dedup/report behavior
  and has five known failures unrelated to the optimization.

## Local Data State
On Nov 12, 2026, local `norsu_ledger` was cleared: both ledger tables, students,
academic terms, and Graduate/Law courses are empty. One assessment form and five
public form submissions remain. See
`plan/finished/clear_graduate_law_ledger_data.md`.

## Completed Plan
`plan/finished/optimize_graduate_import.md` (Nov 13, 2026).

## Next Action
Retry `Graduate School Ledger (New) (3).xlsx`; the `FF` header fix should import
its rows. Then address the Law semester fallback in
`plan/fix_old_new_ledger_workbook_import.md`.
