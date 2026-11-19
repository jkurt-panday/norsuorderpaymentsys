<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'data' => [
                'staff_count' => User::where('role', UserRole::Staff->value)->count(),
                'admin_count' => User::where('role', UserRole::Admin->value)->count(),
                'cashier_count' => User::where('role', UserRole::Cashier->value)->count(),
                'recent_activity' => ActivityLog::latest()->limit(10)->get()->map(fn (ActivityLog $log): array => $this->activityData($log))->values(),
            ],
        ]);
    }

    public function activityLog(Request $request): JsonResponse
    {
        $query = ActivityLog::query();
        $search = trim((string) $request->query('activity_search', ''));
        $action = (string) $request->query('activity_action', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(actor_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(actor_role) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(action) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(subject_type) LIKE ?', [$like]);
            });
        }

        if ($action !== '') {
            $action === 'created'
                ? $query->whereIn('action', ['created', 'user.created'])
                : $query->where('action', $action);
        }
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $allowedSorts = ['created_at', 'action', 'actor_name', 'subject_type'];
        $sort = (string) $request->query('sort', 'created_at');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $query->orderBy($sort, $direction);

        $paginator = $query->paginate(min(max($request->integer('per_page', 10), 1), 100))->withQueryString();

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (ActivityLog $log): array => $this->activityData($log))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => ['next' => $paginator->nextPageUrl(), 'prev' => $paginator->previousPageUrl()],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::withTrashed();
        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');

        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(email) LIKE ?', [$like]));
        }
        if (in_array($role, array_column(UserRole::cases(), 'value'), true)) {
            $query->where('role', $role);
        }

        $allowedSorts = ['name', 'email', 'email_verified_at', 'created_at', 'deleted_at'];
        $sort = (string) $request->query('sort', 'created_at');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy(in_array($sort, $allowedSorts, true) ? $sort : 'created_at', $direction);

        $paginator = $query->paginate(min(max($request->integer('per_page', 10), 1), 100))->withQueryString();

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (User $user): array => $this->userData($user))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => ['next' => $paginator->nextPageUrl(), 'prev' => $paginator->previousPageUrl()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->role = $validated['role'];
        $user->save();

        return response()->json(['message' => 'User created successfully.', 'data' => $this->userData($user)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        if (! empty($validated['password']) && Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'The new password cannot be the same as your current password.', 'errors' => ['password' => ['The new password cannot be the same as your current password.']]], 422);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return response()->json(['message' => 'User updated successfully.', 'data' => $this->userData($user)]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot change the status of your own account.'], 422);
        }
        $user->trashed() ? $user->restore() : $user->delete();

        return response()->json(['message' => $user->trashed() ? 'User deactivated successfully.' : 'User activated successfully.', 'data' => $this->userData($user->fresh())]);
    }

    public function destroy(Request $request, int $id): Response|JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }
        $user->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'deleted_at' => $user->deleted_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function activityData(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'event' => $log->eventCategory(),
            'description' => $log->description,
            'actor_name' => $log->actor_name,
            'actor_role' => $log->actor_role,
            'type' => $log->subjectTypeLabel(),
            'created_at' => $log->created_at?->toISOString(),
            'changes' => is_array($log->meta) && is_array($log->meta['changes'] ?? null) ? $log->meta['changes'] : [],
        ];
    }
}
