<?php

namespace App\Http\Controllers\Api;

use App\Exports\GraduateLedgerExport;
use App\Exports\LawSchoolLedgerExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GraduateLedgerController;
use App\Http\Controllers\LawSchoolLedgerController;
use App\Jobs\SendGraduateLedgerStatementEmail;
use App\Jobs\SendLawLedgerStatementEmail;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Services\LedgerImportResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LedgerController extends Controller
{
    private function model(string $ledger): string
    {
        abort_unless(in_array($ledger, ['graduate', 'law'], true), 404);

        return $ledger === 'graduate' ? GraduateLedger::class : LawSchoolLedger::class;
    }

    private function college(string $ledger): string
    {
        return $ledger === 'graduate' ? 'Graduate School' : 'School of Law';
    }

    public function index(Request $request, string $ledger): JsonResponse
    {
        $model = $this->model($ledger);
        $query = $model::query()->with(['student', 'course', 'academicTerm', 'inputByUser:id,name']);
        if ($request->filled('search')) {
            $like = '%'.mb_strtolower((string) $request->query('search')).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(reference_number) LIKE ?', [$like])->orWhereHas('student', fn ($s) => $s->whereRaw('LOWER(first_name) LIKE ?', [$like])->orWhereRaw('LOWER(last_name) LIKE ?', [$like]));
            });
        }
        foreach (['entry_type', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->query('date_to'));
        }
        $query->latest('id');
        $p = $query->paginate(min(max($request->integer('per_page', 15), 1), 100))->withQueryString();
        $rows = $p->getCollection()->map(fn ($r) => $this->row($r))->values();

        return response()->json(['data' => $rows, 'meta' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()], 'links' => ['next' => $p->nextPageUrl(), 'prev' => $p->previousPageUrl()]]);
    }

    public function store(Request $request, string $ledger): JsonResponse
    {
        if (is_array($request->input('items'))) {
            $items = $request->input('items');
            abort_if(count($items) < 1 || count($items) > 50, 422, 'Items must contain between 1 and 50 transactions.');
            $base = $request->all();
            unset($base['items']);
            $created = DB::transaction(fn () => array_map(function (array $item) use ($ledger, $base) {
                $payload = array_merge($base, $item);

                return $this->model($ledger)::create($this->validated(new Request($payload), $ledger));
            }, $items));

            return response()->json(['message' => 'Ledger transactions created successfully.', 'data' => array_map(fn ($r) => $this->row($r->load(['student', 'course', 'academicTerm'])), $created)], 201);
        }
        $data = $this->validated($request, $ledger);
        $record = $this->model($ledger)::create($data);

        return response()->json(['message' => 'Ledger transaction created successfully.', 'data' => $this->row($record->load(['student', 'course', 'academicTerm']))], 201);
    }

    public function update(Request $request, string $ledger, int|string $id): JsonResponse
    {
        $record = $this->model($ledger)::findOrFail((int) $id);
        $record->update($this->validated($request, $ledger, $record->id));

        return response()->json(['message' => 'Ledger transaction updated successfully.', 'data' => $this->row($record->refresh()->load(['student', 'course', 'academicTerm']))]);
    }

    public function cohortStore(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $rows = $request->input('cohort_rows');
        if (! is_array($rows) || count($rows) < 1 || count($rows) > 500) {
            abort(422, 'cohort_rows must contain between 1 and 500 students.');
        }
        $base = $request->all();
        unset($base['cohort_rows']);
        $created = DB::transaction(fn () => array_map(function (array $row) use ($ledger, $base) {
            return $this->model($ledger)::create($this->validated(new Request(array_merge($base, $row)), $ledger));
        }, $rows));

        return response()->json(['message' => 'Cohort ledger transactions created successfully.', 'created_count' => count($created), 'data' => array_map(fn ($r) => $this->row($r), $created)], 201);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $limit = min(max($request->integer('limit', 50), 1), 100);
        $students = Student::query()->where(function ($q) use ($term) {
            $like = '%'.mb_strtolower($term).'%';
            $q->whereRaw('LOWER(first_name) LIKE ?', [$like])->orWhereRaw('LOWER(last_name) LIKE ?', [$like])->orWhereRaw('LOWER(student_number) LIKE ?', [$like]);
        })->orderBy('last_name')->limit($limit)->get(['id', 'student_number', 'first_name', 'middle_name', 'last_name']);

        return response()->json(['data' => $students->map(fn (Student $s) => ['id' => $s->id, 'student_number' => $s->student_number, 'name' => $s->full_name])->values()]);
    }

    public function import(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $request->validate(['file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:51200'], 'preset_course_id' => ['nullable', 'integer', 'exists:courses,id'], 'preset_academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id']]);
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return response()->json(['message' => 'The uploaded ledger file is invalid.'], 422);
        }
        $controller = $ledger === 'graduate' ? app(GraduateLedgerController::class) : app(LawSchoolLedgerController::class);
        $response = $controller->import($request);
        $session = $response->getSession();
        $message = (string) ($session->get('success') ?? $session->get('error') ?? 'Ledger import completed.');
        $result = LedgerImportResult::fromSession($session->get('ledger_import_result'));
        if ($result === null) {
            $result = new LedgerImportResult(0, 0, 0, []);
        }

        return response()->json(
            ['message' => $message] + $result->toArray(),
            $session->has('error') ? 422 : 200,
        );
    }

    public function export(Request $request): BinaryFileResponse
    {
        $ledger = (string) $request->route('ledger');
        $query = $this->model($ledger)::query()->with(['student', 'course', 'academicTerm', 'inputByUser:id,name'])->latest('id');
        $export = $ledger === 'graduate' ? new GraduateLedgerExport($query) : new LawSchoolLedgerExport($query);

        return Excel::download($export, $ledger.'_ledger_export_'.now()->format('Ymd_His').'.xlsx');
    }

    public function pdf(Request $request): Response
    {
        $ledger = (string) $request->route('ledger');
        $student = Student::findOrFail($request->integer('student_id'));
        $records = $this->model($ledger)::with(['student', 'course', 'academicTerm'])->where('student_id', $student->id)->orderBy('id')->get();
        $view = $ledger === 'graduate' ? 'pdf.student-ledger-statement' : 'pdf.law-student-ledger-statement';
        $pdf = Pdf::view($view, ['student' => $student, 'studentName' => $student->full_name, 'records' => $records, 'summary' => [], 'generatedAt' => now()->format('Y-m-d')])->format('a4')->generatePdfContent();

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Statement_'.$student->id.'.pdf"']);
    }

    public function emailRecipients(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $model = $this->model($ledger);
        $students = $model::query()->with('student')->whereHas('student', fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''))->get()->pluck('student')->unique('id')->values();

        return response()->json(['data' => $students->map(fn (Student $s) => ['id' => $s->id, 'student_number' => $s->student_number, 'name' => $s->full_name, 'email' => $s->email])->values()]);
    }

    public function sendEmails(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1', 'max:500'], 'student_ids.*' => ['integer', 'exists:students,id'], 'school_year' => ['nullable', 'string', 'max:20'], 'semester' => ['nullable', 'string', 'max:50'], 'subject' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:2000']]);
        foreach ($data['student_ids'] as $id) {
            $job = $ledger === 'law'
                ? new SendLawLedgerStatementEmail((int) $id, $data['school_year'] ?? null, $data['semester'] ?? null, $data['subject'] ?? null, $data['note'] ?? null)
                : new SendGraduateLedgerStatementEmail((int) $id, $data['school_year'] ?? null, $data['semester'] ?? null, $data['subject'] ?? null, $data['note'] ?? null);
            dispatch($job);
        }

        return response()->json(['message' => 'Ledger statement emails queued successfully.', 'queued_count' => count($data['student_ids'])]);
    }

    public function destroy(Request $request, int|string $id): Response|JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $this->model($ledger)::findOrFail((int) $id)->delete();

        return response()->noContent();
    }

    public function balance(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $student = Student::findOrFail((int) $request->route('student'));
        $records = $this->model($ledger)::where('student_id', $student->id)->with(['course', 'academicTerm'])->latest('transaction_date')->get();
        $charges = (float) $records->where('entry_type', 'ar')->sum('amount');
        $payments = (float) $records->whereIn('entry_type', ['payment', 'adjustment'])->sum('amount');

        return response()->json(['student' => ['id' => $student->id, 'student_number' => $student->student_number, 'name' => $student->full_name], 'summary' => ['total_charges' => $charges, 'total_payments' => $payments, 'outstanding_balance' => $ledger === 'law' ? $charges - $payments : $charges - $payments], 'transactions' => $records->map(fn ($r) => $this->row($r))->values()]);
    }

    private function validated(Request $request, string $ledger, ?int $ignore = null): array
    {
        $table = $ledger === 'graduate' ? 'graduate_ledgers' : 'law_school_ledgers';
        $data = $request->validate(['student_id' => ['required', 'exists:students,id'], 'course_id' => ['required', Rule::exists('courses', 'id')->where('course_college', $this->college($ledger))], 'academic_term_id' => ['required', 'exists:academic_terms,id'], 'entry_type' => ['required', Rule::in(['ar', 'payment', 'adjustment'])], 'units' => ['nullable', 'numeric', 'min:0'], 'transaction_date' => ['required', 'date'], 'reference_number' => ['nullable', 'string', 'max:255'], 'particulars' => ['required', 'string', 'max:255'], 'rate' => ['required', 'numeric', 'min:0'], 'amount' => ['required', 'numeric', 'min:0'], 'remarks' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:30'], 'membership' => ['nullable', 'string', 'max:50'], 'latin_honor' => ['nullable', 'string', 'max:100'], 'discount_amount' => ['nullable', 'numeric', 'min:0']]);
        $data['input_by'] = $request->user()?->id;
        $data['status'] ??= $ledger === 'law' ? 'Paid' : 'posted';

        return $data;
    }

    private function row($r): array
    {
        return ['id' => $r->id, 'student_id' => $r->student_id, 'course_id' => $r->course_id, 'academic_term_id' => $r->academic_term_id, 'entry_type' => $r->entry_type, 'units' => $r->units, 'transaction_date' => $r->transaction_date?->format('Y-m-d'), 'reference_number' => $r->reference_number, 'particulars' => $r->particulars, 'rate' => $r->rate, 'amount' => $r->amount, 'remarks' => $r->remarks, 'status' => $r->status, 'student' => $r->student?->only(['id', 'student_number', 'first_name', 'last_name']), 'course' => $r->course?->only(['id', 'course_code', 'course_desc', 'course_college']), 'academic_term' => $r->academicTerm?->only(['id', 'school_year', 'semester'])];
    }
}
