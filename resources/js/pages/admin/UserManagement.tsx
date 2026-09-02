import { Head, router, useForm, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import {
    Users,
    Plus,
    Pencil,
    Eye,
    Shield,
    Mail,
    Lock,
    Search,
    RefreshCw,
    ChevronLeft,
    ChevronRight,
    Power,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import AdminLayout from '@/layouts/admin/layout';
import {
    update as updateAdminUser,
    toggle as toggleAdminUser,
} from '@/routes/admin/users';
import { store as storeAdminUser } from '@/routes/admin/users';

const routes = { admin: { users: { store: storeAdminUser } } };

interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'staff' | 'client';
    email_verified_at: string | null;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
}

interface UsersPaginator {
    data: User[];
    links: { url: string | null; label: string; active: boolean }[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface UserFilters {
    search?: string;
    role?: string;
    sort?: string;
    direction?: string;
}

const ROLE_BADGE_CLASSES: Record<string, string> = {
    admin: 'bg-emerald-100 text-emerald-800',
    staff: 'bg-blue-100 text-blue-800',
    cashier: 'bg-violet-100 text-violet-800',
    client: 'bg-slate-100 text-slate-800',
};

const ROLE_OPTIONS = [
    { value: 'admin', label: 'Admin' },
    { value: 'staff', label: 'Staff' },
    { value: 'cashier', label: 'Cashier' },
    { value: 'client', label: 'Client' },
];

function formatDateTime(value?: string | null) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function AdminUserManagement() {
    const {
        users = { data: [] },
        userFilters = {},
        auth,
    } = usePage<{
        users: UsersPaginator;
        userFilters: UserFilters;
        auth: { user: { id: number } | null };
    }>().props;

    const currentUserId = auth?.user?.id;

    const rows: User[] = users?.data ?? [];

    const [search, setSearch] = useState(userFilters?.search ?? '');
    const [roleFilter, setRoleFilter] = useState(userFilters?.role ?? '');
    const [sortKey, setSortKey] = useState(userFilters?.sort ?? '');
    const [sortDir, setSortDir] = useState(userFilters?.direction ?? '');
    const [isNavigating, setIsNavigating] = useState(false);
    const [isResetting, setIsResetting] = useState(false);
    const [pageJumpOpen, setPageJumpOpen] = useState(false);
    const [pageJumpInput, setPageJumpInput] = useState('');

    // Form state for create/edit
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [userToToggle, setUserToToggle] = useState<User | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        role: 'staff',
    });

    const currentPage = users?.meta?.current_page ?? users?.current_page ?? 1;
    const lastPage = users?.meta?.last_page ?? users?.last_page ?? 1;
    const links = users?.links ?? [];

    const effectiveSortKey = sortKey;
    const effectiveSortDir = sortDir;

    const navigateWithParams = (
        overrides: Record<string, string | undefined>,
    ) => {
        const url = new URL(window.location.href);
        for (const [key, value] of Object.entries(overrides)) {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        }
        url.searchParams.delete('page');

        router.get(
            url.pathname + url.search,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['users'],
                showProgress: false,
                onStart: () => setIsNavigating(true),
                onFinish: () => setIsNavigating(false),
            },
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        navigateWithParams({
            search: search.trim() || undefined,
            role: roleFilter === 'All' ? undefined : roleFilter || undefined,
            sort: sortKey || undefined,
            direction: sortDir || undefined,
        });
    };

    const handleRoleChange = (value: string | null) => {
        const selected = value ?? 'All';
        setRoleFilter(selected);
        navigateWithParams({
            search: search.trim() || undefined,
            role: selected === 'All' ? undefined : selected,
            sort: sortKey || undefined,
            direction: sortDir || undefined,
        });
    };

    const handleSortClick = (columnKey: string) => {
        const isActive = effectiveSortKey === columnKey;
        const currentDir = effectiveSortDir;

        if (!isActive) {
            const nextDir = 'asc';
            setSortKey(columnKey);
            setSortDir(nextDir);
            navigateWithParams({
                sort: columnKey,
                direction: nextDir,
                search: search.trim() || undefined,
                role:
                    roleFilter === 'All' ? undefined : roleFilter || undefined,
            });
        } else if (currentDir === 'asc') {
            setSortKey(columnKey);
            setSortDir('desc');
            navigateWithParams({
                sort: columnKey,
                direction: 'desc',
                search: search.trim() || undefined,
                role:
                    roleFilter === 'All' ? undefined : roleFilter || undefined,
            });
        } else {
            setSortKey('');
            setSortDir('');
            navigateWithParams({
                sort: undefined,
                direction: undefined,
                search: search.trim() || undefined,
                role:
                    roleFilter === 'All' ? undefined : roleFilter || undefined,
            });
        }
    };

    const handleReset = () => {
        setSearch('');
        setRoleFilter('');
        setSortKey('');
        setSortDir('');

        router.get(
            window.location.pathname,
            {},
            {
                preserveScroll: true,
                replace: true,
                only: ['users'],
                showProgress: false,
                onStart: () => setIsResetting(true),
                onFinish: () => setIsResetting(false),
            },
        );
    };

    const navigateToPage = (page: number) => {
        const url = new URL(window.location.href);
        if (page > 1) {
            url.searchParams.set('page', String(page));
        } else {
            url.searchParams.delete('page');
        }

        router.get(
            url.pathname + url.search,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['users'],
                showProgress: false,
                onStart: () => setIsNavigating(true),
                onFinish: () => setIsNavigating(false),
            },
        );
    };

    const isBusy = isNavigating || isResetting;

    const openCreateDialog = () => {
        setEditingUser(null);
        reset('name', 'email', 'password', 'role');
        setData('role', 'staff');
        setDialogOpen(true);
    };

    const openEditDialog = (user: User) => {
        setEditingUser(user);
        setData('name', user.name);
        setData('email', user.email);
        setData('password', '');
        setData('role', user.role);
        setDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (editingUser) {
            put(updateAdminUser.url({ id: editingUser.id }), {
                onSuccess: () => {
                    toast.success('User updated successfully!');
                    setDialogOpen(false);
                    setEditingUser(null);
                    reset('name', 'email', 'password', 'role');
                },
            });
        } else {
            post(routes.admin.users.store(), {
                onSuccess: () => {
                    toast.success('User added successfully!');
                    setDialogOpen(false);
                    reset('name', 'email', 'password', 'role');
                },
            });
        }
    };

    const confirmToggle = (user: User) => {
        if (user.id === currentUserId) {
            toast.error('You cannot deactivate your own account', {
                description: 'Currently logged in',
            });
            return;
        }
        setUserToToggle(user);
        setIsDeleteDialogOpen(true);
    };

    const handleToggle = () => {
        if (userToToggle) {
            const willActivate = !!userToToggle.deleted_at;
            router.post(
                toggleAdminUser.url({ id: userToToggle.id }),
                { _method: 'PUT' },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success(
                            willActivate
                                ? 'Account reactivated successfully!'
                                : 'Account deactivated successfully!',
                        );
                        setIsDeleteDialogOpen(false);
                        setUserToToggle(null);
                    },
                },
            );
        }
    };

    const COLUMNS: { label: string; width?: string; sortable?: string }[] = [
        { label: 'Name', sortable: 'name' },
        { label: 'Email', sortable: 'email' },
        { label: 'Role', width: 'w-[130px]' },
        {
            label: 'Active',
            width: 'w-[120px]',
            sortable: 'deleted_at',
        },
        { label: 'Created', width: 'w-[180px]', sortable: 'created_at' },
        { label: 'Actions', width: 'w-[120px]' },
    ];

    return (
        <AdminLayout>
            <Head title="User Management" />

            <div className="mx-auto max-w-7xl">
                <div className="mb-8 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-950">
                            User Management
                        </h1>
                        <p className="text-sm text-slate-500">
                            Manage system users, roles, and access permissions.
                        </p>
                    </div>
                    <Button onClick={openCreateDialog} className="gap-2">
                        <Plus className="h-4 w-4" />
                        Add User
                    </Button>
                </div>

                <Card className="border border-slate-200 bg-white shadow-sm">
                    <div className="relative h-0.5 w-full overflow-hidden bg-transparent">
                        {isBusy && (
                            <div className="absolute inset-0 animate-[table-loading-bar_1s_ease-in-out_infinite] bg-blue-500" />
                        )}
                    </div>
                    <style>{`
                        @keyframes table-loading-bar {
                            0% { transform: translateX(-100%); }
                            100% { transform: translateX(100%); }
                        }
                    `}</style>

                    <CardHeader className="border-b border-slate-200 px-6 py-3.5">
                        <form
                            onSubmit={handleSearchSubmit}
                            className="flex w-full flex-wrap items-center gap-2"
                        >
                            <div className="relative min-w-[200px] flex-1 sm:max-w-xs">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 h-4 w-4 text-slate-400" />
                                <Input
                                    type="search"
                                    placeholder="Search name, email, role..."
                                    className="h-9 pl-8 text-xs"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>

                            <Select
                                value={roleFilter || 'All'}
                                onValueChange={handleRoleChange}
                            >
                                <SelectTrigger className="h-9 w-full text-xs sm:w-36">
                                    <SelectValue placeholder="All roles" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="All">
                                        All roles
                                    </SelectItem>
                                    {ROLE_OPTIONS.map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Button
                                type="submit"
                                size="sm"
                                className="h-9 bg-blue-600 text-xs text-white hover:bg-blue-700"
                            >
                                <Search className="mr-1.5 h-3.5 w-3.5" />
                                Search
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-9 text-xs"
                                onClick={handleReset}
                                disabled={isResetting}
                                title="Reset search, filter and sorting"
                            >
                                <RefreshCw
                                    className={`mr-1.5 h-3.5 w-3.5 ${isResetting ? 'animate-spin' : ''}`}
                                />
                                Reset
                            </Button>
                        </form>
                    </CardHeader>

                    <CardContent className="p-0">
                        <Table
                            className={`transition-opacity duration-150 ${isBusy ? 'opacity-60' : 'opacity-100'}`}
                        >
                            <TableHeader>
                                <TableRow className="bg-slate-50 hover:bg-slate-50">
                                    {COLUMNS.map((col) => {
                                        const sortKeyName = col.sortable;
                                        const isActive = sortKeyName
                                            ? effectiveSortKey === sortKeyName
                                            : false;

                                        return (
                                            <TableHead
                                                key={col.label}
                                                className={`${col.width || ''} ${
                                                    sortKeyName
                                                        ? 'cursor-pointer transition-colors select-none'
                                                        : ''
                                                } ${isActive ? 'bg-blue-50/80 font-bold text-blue-600' : 'hover:text-slate-900'}`}
                                                onClick={
                                                    sortKeyName
                                                        ? () =>
                                                              handleSortClick(
                                                                  sortKeyName,
                                                              )
                                                        : undefined
                                                }
                                            >
                                                <span className="inline-flex items-center gap-1.5">
                                                    {col.label}
                                                    {sortKeyName &&
                                                        (isActive ? (
                                                            effectiveSortDir ===
                                                            'asc' ? (
                                                                <ChevronLeft className="h-4 w-4 stroke-[2.5] text-blue-600" />
                                                            ) : (
                                                                <ChevronRight className="h-4 w-4 stroke-[2.5] text-blue-600" />
                                                            )
                                                        ) : (
                                                            <ChevronRight className="h-3.5 w-3.5 rotate-90 text-slate-400" />
                                                        ))}
                                                </span>
                                            </TableHead>
                                        );
                                    })}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="py-10 text-center text-sm text-slate-500"
                                        >
                                            No users found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    rows.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium text-slate-900">
                                                {user.name}
                                            </TableCell>
                                            <TableCell className="text-sm text-slate-700">
                                                {user.email}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="secondary"
                                                    className={
                                                        ROLE_BADGE_CLASSES[
                                                            user.role
                                                        ] ||
                                                        'bg-slate-100 text-slate-800'
                                                    }
                                                >
                                                    {user.role
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                        user.role.slice(1)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-slate-600">
                                                {user.deleted_at ? (
                                                    <Badge
                                                        variant="secondary"
                                                        className="bg-rose-100 text-rose-800"
                                                    >
                                                        Deactivated
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="secondary"
                                                        className="bg-emerald-100 text-emerald-800"
                                                    >
                                                        Active
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm text-slate-500">
                                                {formatDateTime(
                                                    user.created_at,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEditDialog(user)
                                                        }
                                                        title="Edit user"
                                                        className="text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            confirmToggle(user)
                                                        }
                                                        title={
                                                            user.deleted_at
                                                                ? 'Activate user'
                                                                : 'Deactivate user'
                                                        }
                                                        className={
                                                            user.deleted_at
                                                                ? 'text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700'
                                                                : 'text-slate-600 hover:bg-rose-50 hover:text-rose-600'
                                                        }
                                                    >
                                                        <Power className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>

                        {rows.length > 0 && (
                            <div className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 sm:px-5">
                                <p className="shrink-0 text-xs text-slate-500 sm:text-sm">
                                    Showing {users.from ?? 0}-{users.to ?? 0} of{' '}
                                    {users.total} results
                                </p>

                                {lastPage > 3 ? (
                                    (() => {
                                        const prevLink = links.find((l) =>
                                            l.label
                                                .replace(/&laquo;|&raquo;/g, '')
                                                .trim()
                                                .toLowerCase()
                                                .includes('previous'),
                                        );
                                        const nextLink = links.find((l) =>
                                            l.label
                                                .replace(/&laquo;|&raquo;/g, '')
                                                .trim()
                                                .toLowerCase()
                                                .includes('next'),
                                        );

                                        return (
                                            <div className="flex shrink-0 items-center gap-1.5">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    disabled={!prevLink?.url}
                                                    onClick={() =>
                                                        navigateToPage(
                                                            currentPage - 1,
                                                        )
                                                    }
                                                    aria-label="Previous page"
                                                    className="h-8 w-8 shrink-0 rounded-md text-sm"
                                                >
                                                    <ChevronLeft className="h-4 w-4" />
                                                </Button>

                                                <Popover
                                                    open={pageJumpOpen}
                                                    onOpenChange={(open) => {
                                                        setPageJumpOpen(open);
                                                        if (open)
                                                            setPageJumpInput(
                                                                String(
                                                                    currentPage,
                                                                ),
                                                            );
                                                    }}
                                                >
                                                    <PopoverTrigger
                                                        render={
                                                            <button
                                                                type="button"
                                                                className="inline-flex h-8 items-center justify-center gap-1 rounded-md border border-slate-200 px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
                                                            >
                                                                Page{' '}
                                                                {currentPage} of{' '}
                                                                {lastPage}
                                                            </button>
                                                        }
                                                    />
                                                    <PopoverContent
                                                        align="center"
                                                        className="w-48 space-y-2 p-2"
                                                    >
                                                        <form
                                                            onSubmit={(e) => {
                                                                e.preventDefault();
                                                                const parsed =
                                                                    Number(
                                                                        pageJumpInput,
                                                                    );
                                                                if (
                                                                    !Number.isNaN(
                                                                        parsed,
                                                                    ) &&
                                                                    parsed >=
                                                                        1 &&
                                                                    parsed <=
                                                                        lastPage
                                                                ) {
                                                                    navigateToPage(
                                                                        parsed,
                                                                    );
                                                                    setPageJumpOpen(
                                                                        false,
                                                                    );
                                                                }
                                                            }}
                                                            className="flex items-center gap-1.5"
                                                        >
                                                            <input
                                                                type="number"
                                                                min={1}
                                                                max={lastPage}
                                                                value={
                                                                    pageJumpInput
                                                                }
                                                                onChange={(e) =>
                                                                    setPageJumpInput(
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder={`1–${lastPage}`}
                                                                className="h-8 w-full min-w-0 rounded-md border border-slate-200 px-2 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-blue-200"
                                                                autoFocus
                                                            />
                                                            <button
                                                                type="submit"
                                                                className="h-8 shrink-0 rounded-md bg-blue-900 px-2.5 text-xs font-semibold text-white transition-colors hover:bg-blue-950"
                                                            >
                                                                Go
                                                            </button>
                                                        </form>
                                                        <div className="max-h-56 space-y-0.5 overflow-y-auto border-t border-slate-100 pt-1.5">
                                                            {Array.from(
                                                                {
                                                                    length: lastPage,
                                                                },
                                                                (_, i) => i + 1,
                                                            ).map((page) => (
                                                                <button
                                                                    key={page}
                                                                    type="button"
                                                                    onClick={() => {
                                                                        navigateToPage(
                                                                            page,
                                                                        );
                                                                        setPageJumpOpen(
                                                                            false,
                                                                        );
                                                                    }}
                                                                    className={`flex w-full items-center rounded-md px-2 py-1.5 text-left text-sm transition-colors ${page === currentPage ? 'bg-blue-50 font-medium text-blue-900' : 'text-slate-600 hover:bg-slate-50'}`}
                                                                >
                                                                    Page {page}
                                                                </button>
                                                            ))}
                                                        </div>
                                                    </PopoverContent>
                                                </Popover>

                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    disabled={!nextLink?.url}
                                                    onClick={() =>
                                                        navigateToPage(
                                                            currentPage + 1,
                                                        )
                                                    }
                                                    aria-label="Next page"
                                                    className="h-8 w-8 shrink-0 rounded-md text-sm"
                                                >
                                                    <ChevronRight className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        );
                                    })()
                                ) : (
                                    <div className="flex flex-nowrap items-center gap-1">
                                        {links.map((link, i) => {
                                            const rawLabel = link.label
                                                .replace(/&laquo;|&raquo;/g, '')
                                                .trim();
                                            const isPrev =
                                                rawLabel.toLowerCase() ===
                                                'previous';
                                            const isNext =
                                                rawLabel.toLowerCase() ===
                                                'next';

                                            return (
                                                <Button
                                                    key={i}
                                                    type="button"
                                                    size="icon"
                                                    variant={
                                                        link.active
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    disabled={!link.url}
                                                    onClick={() =>
                                                        link.url &&
                                                        navigateToPage(
                                                            Number(
                                                                link.label,
                                                            ) || 1,
                                                        )
                                                    }
                                                    aria-label={
                                                        isPrev
                                                            ? 'Previous page'
                                                            : isNext
                                                              ? 'Next page'
                                                              : `Page ${link.label}`
                                                    }
                                                    className={`h-8 w-8 shrink-0 rounded-md text-sm ${link.active ? 'bg-blue-900 text-white hover:bg-blue-950' : ''}`}
                                                >
                                                    {isPrev ? (
                                                        <ChevronLeft className="h-4 w-4" />
                                                    ) : isNext ? (
                                                        <ChevronRight className="h-4 w-4" />
                                                    ) : (
                                                        link.label
                                                    )}
                                                </Button>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Create/Edit User Dialog */}
                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="max-w-md sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>
                                {editingUser ? 'Edit User' : 'Create New User'}
                            </DialogTitle>
                            <DialogDescription>
                                {editingUser
                                    ? editingUser.id === currentUserId
                                        ? 'Update your name, email, or password. Role changes are restricted for the current account.'
                                        : 'Update user information and role.'
                                    : 'Fill in the details below to create a new system user.'}
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleSubmit}>
                            <div className="grid gap-4 py-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Full Name</Label>
                                    <Input
                                        id="name"
                                        placeholder="e.g., Juan Dela Cruz"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        required
                                    />
                                    {errors.name && (
                                        <p className="text-xs font-semibold text-destructive">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="email">Email Address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        placeholder="name@norsu.edu.ph"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                        required
                                    />
                                    {errors.email && (
                                        <p className="text-xs font-semibold text-destructive">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password">
                                        {editingUser
                                            ? 'New Password (leave blank to keep current)'
                                            : 'Password'}
                                    </Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        required={!editingUser}
                                    />
                                    {errors.password && (
                                        <p className="text-xs font-semibold text-destructive">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="role">Role</Label>
                                    <Select
                                        value={data.role}
                                        onValueChange={(value) =>
                                            setData('role', value)
                                        }
                                        disabled={
                                            editingUser?.id === currentUserId
                                        }
                                    >
                                        <SelectTrigger
                                            className={
                                                editingUser?.id ===
                                                currentUserId
                                                    ? 'cursor-not-allowed opacity-50'
                                                    : 'w-full'
                                            }
                                        >
                                            <SelectValue placeholder="Select role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {ROLE_OPTIONS.map((opt) => (
                                                <SelectItem
                                                    key={opt.value}
                                                    value={opt.value}
                                                >
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.role && (
                                        <p className="text-xs font-semibold text-destructive">
                                            {errors.role}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setDialogOpen(false);
                                        setEditingUser(null);
                                        reset(
                                            'name',
                                            'email',
                                            'password',
                                            'role',
                                        );
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Saving...'
                                        : editingUser
                                          ? 'Update User'
                                          : 'Create User'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Toggle Status Confirmation Dialog */}
                <Dialog
                    open={isDeleteDialogOpen}
                    onOpenChange={setIsDeleteDialogOpen}
                >
                    <DialogContent className="max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                {userToToggle?.deleted_at
                                    ? 'Activate User'
                                    : 'Deactivate User'}
                            </DialogTitle>
                            <DialogDescription>
                                Are you sure you want to{' '}
                                {userToToggle?.deleted_at
                                    ? 'activate'
                                    : 'deactivate'}{' '}
                                <strong>{userToToggle?.name}</strong> (
                                {userToToggle?.email})?
                                {!userToToggle?.deleted_at &&
                                    ' This will disable their access.'}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setIsDeleteDialogOpen(false);
                                    setUserToToggle(null);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant={
                                    userToToggle?.deleted_at
                                        ? 'default'
                                        : 'destructive'
                                }
                                onClick={handleToggle}
                                disabled={processing}
                            >
                                {processing
                                    ? userToToggle?.deleted_at
                                        ? 'Activating...'
                                        : 'Deactivating...'
                                    : userToToggle?.deleted_at
                                      ? 'Activate User'
                                      : 'Deactivate User'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AdminLayout>
    );
}
