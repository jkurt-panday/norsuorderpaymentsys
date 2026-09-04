<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared `index()` logic for simple CRUD resource tables (Membership, UACS,
 * Bank, Payment, ...) that all follow the same shape: search + paginate +
 * render an Inertia page + surface flash messages.
 *
 * Each concrete controller just sets a handful of properties below and
 * gets a fully working, search-and-poll-ready index() for free. Create,
 * store, edit, update, and destroy stay in each individual controller,
 * since those genuinely differ per resource (different validation rules,
 * different "is this still in use" checks before delete, etc.).
 */
abstract class BaseResourceController extends Controller
{
    /** Fully-qualified Eloquent model class, e.g. Membership::class */
    protected string $model;

    /**
     * Columns to search against when `?search=` is present. Uses a simple
     * `where(...)->orWhere(...)` LIKE match across all of them.
     * e.g. ['member_code', 'member_desc']
     */
    protected array $searchableColumns = [];

    /**
     * Extra sort columns applied after the primary orderBy, e.g. for Bank
     * Account's "sort by bank name, then account name" default ordering.
     * [ ['column' => 'account_name', 'direction' => 'asc'] ]
     */
    protected array $secondaryOrderBy = [];

    /** Inertia view path for the index page, e.g. 'staff/memberships/membership' */
    protected string $indexView;

    /**
     * The prop key the paginated data is passed under, e.g. 'memberships'.
     * Must match whatever the frontend page destructures
     * (and whatever `resourceKey` is passed to ResourceTable/RequestTable).
     */
    protected string $resourceKey;

    /** Relations to eager-load before querying, e.g. ['formInputs'] */
    protected array $with = [];

    protected int $perPage = 10;

    protected string $orderBy = 'id';

    protected string $orderDirection = 'asc';

    /**
     * Allowlist of columns that `?sort=` is permitted to use. Required for
     * sorting to actually take effect — this is a security boundary, not
     * just a nicety: without it, `?sort=` is ignored rather than passed
     * straight into orderBy() from user input.
     * e.g. ['member_code', 'member_desc', 'created_at']
     */
    protected array $sortableColumns = [];

    /**
     * Maps a filter query param name to the actual column it filters,
     * e.g. ['status' => 'status'] or ['type' => 'request_type'] if the
     * frontend's FilterFieldDef.key differs from the real column name.
     * Applied as a simple equality `where()` for each key present in the
     * request. Like $sortableColumns, this is an allowlist — only keys
     * declared here are ever applied.
     */
    protected array $filterableColumns = [];

    /**
     * Display a listing of the resource — search, paginate, render, flash.
     * Shared across every controller extending this class.
     */
    /**
     * Display a listing of the resource — search, paginate, render, flash.
     * Shared across every controller extending this class.
     */
    public function index(Request $request): Response
    {
        $query = $this->model::query();

        if (! empty($this->with)) {
            $query->with($this->with);
        }

        if ($search = $request->query('search')) {
            $columns = $this->searchableColumns;
            $searchLower = strtolower($search);

            $query->where(function (Builder $q) use ($columns, $searchLower) {
                foreach ($columns as $column) {
                    $q->orWhereRaw("LOWER({$column}) LIKE ?", ["%{$searchLower}%"]);
                }
            });
        }

        // ---- Filters (allowlisted) ----
        foreach ($this->filterableColumns as $paramKey => $column) {
            $value = $request->query($paramKey);
            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        $query = $this->modifyIndexQuery($query, $request);

        // ---- Sorting (allowlisted — falls back to the default if the
        // requested column isn't explicitly permitted) ----
        $orderBy = $this->orderBy;
        $orderDirection = $this->orderDirection;

        $requestedSort = $request->query('sort');
        if ($requestedSort && in_array($requestedSort, $this->sortableColumns, true)) {
            $orderBy = $requestedSort;
            $orderDirection = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        }

        $query->orderBy($orderBy, $orderDirection);

        // Tie-break on `id`, matching the primary sort's direction, whenever
        // the primary sort column isn't already `id` itself. Without this,
        // rows that share the same value in the sorted column (e.g. several
        // records created on the same date) have no defined relative order —
        // the database is free to return them in any order, which looks like
        // random shuffling to the user every time the page reloads or the
        // sort direction flips. Matching the tie-break direction to the main
        // sort keeps same-value groups internally consistent (e.g. under
        // "Newest", tied same-day records still read newest-created-first).
        if ($orderBy !== 'id') {
            $query->orderBy('id', $orderDirection);
        }

        foreach ($this->secondaryOrderBy as $secondary) {
            $query->orderBy($secondary['column'], $secondary['direction'] ?? 'asc');
        }

        $items = $query
            ->paginate($this->perPage)
            ->withQueryString(); // keeps ?search=/?sort=/?filters=... attached across pagination links

        return Inertia::render($this->indexView, array_merge(
            [
                $this->resourceKey => $items,
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                ],
            ],
            $this->extraIndexProps($request),
        ));
    }

    /**
     * Hook for subclasses that need extra query constraints beyond search
     * (e.g. a status filter, a date range, scoping to the current user).
     * Default: no-op, returns the query unchanged.
     */
    protected function modifyIndexQuery(Builder $query, Request $request): Builder
    {
        return $query;
    }

    /**
     * Hook for subclasses that need to pass additional Inertia props beyond
     * the resource + flash (e.g. dropdown options for a create form on the
     * same page). Default: none.
     */
    protected function extraIndexProps(Request $request): array
    {
        return [];
    }
}
