<?php

namespace App\Http\Controllers\Api;

use App\Exports\UACSExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\AuthorizedOfficial;
use App\Models\BankAccountInfo;
use App\Models\CollegeOffice;
use App\Models\Courses;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\UACS;
use App\Services\UACSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasterDataController extends Controller
{
    private const RESOURCES = [
        'memberships' => Membership::class,
        'payment-options' => PaymentDetailOption::class,
        'bank-accounts' => BankAccountInfo::class,
        'uacs' => UACS::class,
        'authorized-officials' => AuthorizedOfficial::class,
        'courses' => Courses::class,
        'academic-terms' => AcademicTerm::class,
        'college-offices' => CollegeOffice::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $resource = (string) $request->route('resource');
        $model = $this->model($resource);
        $query = $model::query();
        $search = trim((string) $request->query('search', ''));
        $searchColumns = $this->searchColumns($resource);
        if ($search !== '' && $searchColumns !== []) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($query) use ($searchColumns, $like): void {
                foreach ($searchColumns as $column) {
                    $query->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                }
            });
        }
        if ($resource === 'authorized-officials' && $request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        if ($resource === 'courses' && $request->filled('college')) {
            $query->where('course_college', (string) $request->query('college'));
        }
        $sort = (string) $request->query('sort', 'id');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy(in_array($sort, $this->sortableColumns($resource), true) ? $sort : 'id', $direction);
        $paginator = $query->paginate(min(max($request->integer('per_page', 20), 1), 100))->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()],
            'links' => ['next' => $paginator->nextPageUrl(), 'prev' => $paginator->previousPageUrl()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $resource = (string) $request->route('resource');
        $validated = $request->validate($this->rules($resource));
        $model = $this->model($resource);
        $record = DB::transaction(function () use ($resource, $model, $validated) {
            if ($resource === 'authorized-officials') {
                $model::query()->update(['is_active' => false]);
                $validated['is_active'] = true;
            }

            return $model::create($validated);
        });

        return response()->json(['message' => 'Record created successfully.', 'data' => $record], 201);
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        $resource = (string) $request->route('resource');
        $id = (int) $id;
        $model = $this->model($resource);
        $record = $model::query()->findOrFail($id);
        $validated = $request->validate($this->rules($resource, $record->id));
        DB::transaction(function () use ($resource, $record, $validated): void {
            if ($resource === 'authorized-officials') {
                $record::query()->whereKeyNot($record->id)->update(['is_active' => false]);
                $validated['is_active'] = true;
            }
            $record->update($validated);
        });

        return response()->json(['message' => 'Record updated successfully.', 'data' => $record->refresh()]);
    }

    public function destroy(Request $request, string|int $id): Response|JsonResponse
    {
        $resource = (string) $request->route('resource');
        $id = (int) $id;
        $model = $this->model($resource);
        $record = $model::query()->findOrFail($id);
        if (($resource === 'memberships' || $resource === 'payment-options') && $record->formInputs()->exists()) {
            return response()->json(['message' => 'This record is in use and cannot be deleted.'], 409);
        }
        if ($resource === 'bank-accounts' && $record->staffInputs()->exists()) {
            return response()->json(['message' => 'This record is in use and cannot be deleted.'], 409);
        }
        if ($resource === 'uacs' && $record->staffInputs()->exists()) {
            return response()->json(['message' => 'This record is in use and cannot be deleted.'], 409);
        }
        if ($resource === 'authorized-officials' && $record->is_active) {
            return response()->json(['message' => 'The active authorized official cannot be deleted.'], 409);
        }
        $record->delete();

        return response()->noContent();
    }

    public function setActive(int $id): JsonResponse
    {
        $record = AuthorizedOfficial::findOrFail($id);
        DB::transaction(function () use ($record): void {
            AuthorizedOfficial::query()->update(['is_active' => false]);
            $record->update(['is_active' => true]);
        });

        return response()->json(['message' => 'Authorized official activated.', 'data' => $record->refresh()]);
    }

    public function exportUacs(): BinaryFileResponse
    {
        return Excel::download(new UACSExport, 'uacs_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importUacs(Request $request, UACSService $service): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);
        $count = $service->import($request->file('file'));

        return response()->json(['message' => "Imported {$count} UACS records successfully.", 'count' => $count]);
    }

    private function model(string $resource): string
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404, 'Unknown master-data resource.');

        return self::RESOURCES[$resource];
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(string $resource, ?int $ignore = null): array
    {
        $unique = fn (string $table, string $column) => [Rule::unique($table, $column)->ignore($ignore)];

        return match ($resource) {
            'memberships' => ['member_code' => ['required', 'string', 'max:100', ...$unique('memberships', 'member_code')], 'member_desc' => ['required', 'string', 'max:255']],
            'payment-options' => ['payment_desc' => ['required', 'string', 'max:255', ...$unique('payment_detail_options', 'payment_desc')]],
            'bank-accounts' => ['account_name' => ['required', 'string', 'max:255'], 'bank_name' => ['required', 'string', 'max:255'], 'account_num' => ['required', 'string', 'max:100'], 'fund_cluster' => ['required', 'string', 'max:100']],
            'uacs' => ['norsu_object_code' => ['nullable', 'string', 'max:100'], 'norsu_sub_object_code' => ['nullable', 'string', 'max:100'], 'uacs_object_code' => ['nullable', 'string', 'max:100'], 'uacs_sub_object_code' => ['nullable', 'string', 'max:100'], 'rca_code' => ['nullable', 'string', 'max:100'], 'account_title' => ['nullable', 'string', 'max:255']],
            'authorized-officials' => ['name' => ['required', 'string', 'max:255'], 'position' => ['required', 'string', 'max:255'], 'course' => ['nullable', 'string', 'max:255']],
            'courses' => ['course_code' => ['required', 'string', 'max:20', ...$unique('courses', 'course_code')], 'course_desc' => ['required', 'string', 'max:255'], 'course_college' => ['required', Rule::in(['Graduate School', 'School of Law'])]],
            'academic-terms' => ['school_year' => ['required', 'string', 'max:20'], 'semester' => ['required', Rule::in(['First Semester', 'Second Semester', 'Summer'])]],
            'college-offices' => ['name' => ['required', 'string', 'max:255', ...$unique('college_offices', 'name')]],
        };
    }

    /** @return list<string> */
    private function searchColumns(string $resource): array
    {
        return match ($resource) {
            'memberships' => ['member_code', 'member_desc'],
            'payment-options' => ['payment_desc'],
            'bank-accounts' => ['account_name', 'bank_name', 'account_num', 'fund_cluster'],
            'uacs' => ['norsu_object_code', 'norsu_sub_object_code', 'uacs_object_code', 'uacs_sub_object_code', 'rca_code', 'account_title'],
            'authorized-officials' => ['name', 'position', 'course'],
            'courses' => ['course_code', 'course_desc', 'course_college'],
            'academic-terms' => ['school_year', 'semester'],
            'college-offices' => ['name'],
            default => [],
        };
    }

    /** @return list<string> */
    private function sortableColumns(string $resource): array
    {
        $extra = match ($resource) {
            'authorized-officials' => ['is_active'],
            'uacs' => ['norsu_object_code', 'norsu_sub_object_code', 'uacs_object_code', 'uacs_sub_object_code', 'rca_code', 'account_title'],
            default => [],
        };

        return array_values(array_unique(array_merge(['id', 'created_at', 'updated_at'], $this->searchColumns($resource), $extra)));
    }
}
