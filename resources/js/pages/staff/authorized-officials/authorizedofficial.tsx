import { router } from '@inertiajs/react';
import { Users, Shield } from 'lucide-react';
import React, { useEffect } from 'react';
import { toast } from 'sonner';
import {
    create,
    edit,
    destroy,
    setActive,
} from '@/actions/App/Http/Controllers/AuthorizedOfficialController';
import ResourceTable from '@/components/ResourceTable';
import type { PaginatedData, ColumnDef } from '@/components/ResourceTable';
import { flashToast } from '@/utils/flashToast';

// ============ TYPE DEFINITIONS ============
interface AuthorizedOfficialRecord {
    id: number;
    name: string;
    position: string;
    course: string | null;
    is_active: boolean;
    created_at: string;
}

interface FlashProps {
    success?: string;
    error?: string;
}

interface Props {
    authorizedOfficials: PaginatedData<AuthorizedOfficialRecord>;
    flash?: FlashProps;
}

// ============ COMPONENT ============
export default function AuthorizedOfficialIndex({
    authorizedOfficials,
    flash,
}: Props) {
    useEffect(() => {
        if (flash?.success) {
            flashToast('success', flash.success);
        }

        if (flash?.error) {
            flashToast('error', flash.error);
        }
    }, [flash]);

    const handleSetActive = (id: number) => {
        router.put(setActive(id).url, {}, {
            preserveScroll: true,
            onError: () => {
                toast.error('Failed to set this official as active.');
            },
        });
    };

    const columns: ColumnDef<AuthorizedOfficialRecord>[] = [
        {
            header: 'Name',
            sortable: 'name',
            width: '240px',
            render: (row) => (
                <span className="block font-medium text-slate-900">
                    {row.name}
                </span>
            ),
        },
        {
            header: 'Position',
            sortable: 'position',
            render: (row) => row.position,
        },
        {
            header: 'Course / Credentials',
            sortable: 'course',
            render: (row) => row.course ?? '—',
        },
        {
            header: 'Active',
            sortable: 'is_active',
            width: '140px',
            className: 'text-center',
            render: (row) =>
                row.is_active ? (
                    <span className="inline-flex items-center justify-center rounded-full px-2.5 py-1 text-xs font-medium bg-emerald-100 text-emerald-800">
                        Current
                    </span>
                ) : (
                    <button
                        type="button"
                        onClick={() => handleSetActive(row.id)}
                        className="inline-flex items-center justify-center gap-1 rounded-full bg-blue-600 px-3 py-1 text-xs font-medium text-white transition-colors hover:bg-blue-700"
                        title="Set as current signatory"
                    >
                        <Shield className="h-3 w-3" />
                        Set Active
                    </button>
                ),
        },
        {
            header: 'Date added',
            sortable: 'created_at',
            width: '160px',
            className: 'whitespace-nowrap text-slate-600',
            render: (row) =>
                new Date(row.created_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                }),
        },
    ];

    return (
        <ResourceTable<AuthorizedOfficialRecord>
            title="Authorized Officials"
            addLabel="Add Authorized Official"
            addHref={create()}
            columns={columns}
            resource={authorizedOfficials}
            resourceKey="authorizedOfficials"
            pollInterval={15000}
            editHref={(row) => edit(row.id)}
            deleteUrl={(id) => destroy(id).url}
            emptyIcon={Users}
            emptyMessage="No authorized officials found."
            deleteConfirmMessage="Delete this authorized official?"
            sortOptions={[
                { label: 'Newest', sort: 'created_at', direction: 'desc' },
                { label: 'Oldest', sort: 'created_at', direction: 'asc' },
                { label: 'A–Z (Name)', sort: 'name', direction: 'asc' },
            ]}
        />
    );
}
