<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    /**
     * Admin Dashboard - shows stats and recent activity
     */
    public function dashboard(Request $request): Response
    {
        $staffCount = User::where('role', 'staff')->count();
        $adminCount = User::where('role', 'admin')->count();
        $clientCount = User::where('role', 'client')->count();

        $recentActivity = ActivityLog::latest()
            ->take(10)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'event' => $log->eventCategory(),
                'description' => $log->description,
                'actor_name' => $log->actor_name,
                'actor_role' => $log->actor_role,
                'type' => $log->subjectTypeLabel(),
                'created_at' => $log->created_at->toISOString(),
            ]);

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'staffCount' => $staffCount,
                'adminCount' => $adminCount,
                'clientCount' => $clientCount,
            ],
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Display a listing of users with search, filter, and pagination.
     */
    public function index(Request $request): Response
    {
        $search = (string) $request->query('search', '');
        $role = (string) $request->query('role', '');
        $sort = (string) $request->query('sort', '');
        $direction = (string) $request->query('direction', '');

        $allowedSorts = ['name', 'email', 'email_verified_at', 'created_at', 'deleted_at'];

        $query = User::query()->withTrashed();

        if ($search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        if ($role !== '' && in_array($role, ['admin', 'staff', 'client'], true)) {
            $query->where('role', $role);
        }

        if (in_array($sort, $allowedSorts, true) && in_array($direction, ['asc', 'desc'], true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        $users = $query->paginate(10)->withQueryString()->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'deleted_at' => $user->deleted_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ]);

        $userFilters = [
            'search' => $search,
            'role' => $role,
            'sort' => $sort,
            'direction' => $direction,
        ];

        return Inertia::render('admin/UserManagement', [
            'users' => $users,
            'userFilters' => $userFilters,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return back()->with('success', 'New system account registered successfully!');
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($user->id === request()->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully!');
    }

    /**
     * Toggle the specified user active/deactivated status.
     */
    public function toggle(int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($user->id === request()->user()->id) {
            return back()->with('error', 'You cannot change the status of your own account.');
        }

        if ($user->trashed()) {
            $user->restore();

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'User activated successfully!');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deactivated successfully!');
    }

    /**
     * Display the admin activity log.
     */
    public function activityLog(Request $request): Response
    {
        [$activityLogs, $activityFilters] = $this->buildActivityLogs($request);

        return Inertia::render('admin/ActivityLog', [
            'activityLogs' => $activityLogs,
            'activityFilters' => $activityFilters,
        ]);
    }

    /**
     * Build the searchable/paginated activity log query.
     *
     * @return array{
     *     0: LengthAwarePaginator<int, covariant array<string, mixed>>,
     *     1: array{activity_search: string, activity_action: string, sort: string, direction: string, date_from: string, date_to: string}
     * }
     */
    protected function buildActivityLogs(Request $request): array
    {
        $activitySearch = (string) $request->query('activity_search', '');
        $activityAction = (string) $request->query('activity_action', '');
        $activitySort = (string) $request->query('sort', '');
        $activityDir = (string) $request->query('direction', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $allowedSorts = ['created_at', 'action', 'actor_name', 'subject_type'];

        $activityQuery = ActivityLog::query();

        if ($activitySearch !== '') {
            $like = '%'.strtolower($activitySearch).'%';
            $activityQuery->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(actor_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(actor_role) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(action) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(subject_type) LIKE ?', [$like]);
            });
        }

        if ($activityAction !== '') {
            if ($activityAction === 'created') {
                // "created" filter also matches user provisioning entries.
                $activityQuery->whereIn('action', ['created', 'user.created']);
            } else {
                $activityQuery->where('action', $activityAction);
            }
        }

        if ($dateFrom !== '') {
            $activityQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $activityQuery->whereDate('created_at', '<=', $dateTo);
        }

        // Apply explicit column sort (from clickable headers / sort dropdown),
        // otherwise default to newest-first.
        if (in_array($activitySort, $allowedSorts, true)
            && in_array($activityDir, ['asc', 'desc'], true)) {
            if ($activitySort === 'actor_name') {
                $activityQuery->orderByRaw('actor_name IS NULL ASC')
                    ->orderBy('actor_name', $activityDir)
                    ->orderBy('created_at', 'desc');
            } elseif ($activitySort === 'subject_type') {
                $activityQuery->orderByRaw('subject_type IS NULL ASC')
                    ->orderBy('subject_type', $activityDir)
                    ->orderBy('created_at', 'desc');
            } elseif ($activitySort === 'action') {
                $activityQuery->orderBy('action', $activityDir)
                    ->orderBy('created_at', 'desc');
            } else {
                $activityQuery->orderBy('created_at', $activityDir);
            }
        } else {
            $activityQuery->latest();
        }

        $activityLogs = $activityQuery
            ->paginate(10)
            ->withQueryString()
            ->through(function (ActivityLog $log): array {
                $meta = $log->meta;

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'event' => $log->eventCategory(),
                    'description' => $log->description,
                    'actor_name' => $log->actor_name,
                    'actor_role' => $log->actor_role,
                    'type' => $log->subjectTypeLabel(),
                    'created_at' => $log->created_at->toISOString(),
                    'changes' => is_array($meta) && isset($meta['changes']) && is_array($meta['changes'])
                        ? $meta['changes']
                        : [],
                ];
            });

        $activityFilters = [
            'activity_search' => $activitySearch,
            'activity_action' => $activityAction,
            'sort' => $activitySort,
            'direction' => $activityDir,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        return [$activityLogs, $activityFilters];
    }
}
