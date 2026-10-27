import { Head, Link, router } from '@inertiajs/react';
import { Download, Plus, Upload } from 'lucide-react';
import { useRef } from 'react';

import { columns } from './columns';
import type { UacsType } from './columns';
import { Button } from '@/components/base/buttons/button';
import { ClientDataTable } from '@/components/data-table/client-data-table';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';

type UacsProps = {
    uacs: UacsType[];
};

export default function UacsIndex({ uacs }: UacsProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleImport = (file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        router.post('/staff/uacs/import', formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['uacs'] });
            },
        });
    };

    return (
        <>
            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-page-background p-3 sm:p-6">
                <Head title="UACS" />

                <Card className="rounded-2xl border border-border bg-card px-2! py-2! text-card-foreground shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between p-3">
                        <CardTitle className="bg-[#3399FF] bg-clip-text font-['Poppins'] text-3xl font-black tracking-tight text-transparent sm:text-4xl">
                            UACS
                        </CardTitle>
                        <div className="flex flex-wrap items-center gap-2">
                            <a href="/staff/uacs/export" download>
                                <Button className="inline-flex flex-row items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                                    <div className="flex flex-row items-center gap-1.5">
                                        <Download className="h-4 w-4" />
                                        <span>Export</span>
                                    </div>
                                </Button>
                            </a>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                className="hidden"
                                onChange={(event) => {
                                    const file = event.target.files?.[0];

                                    if (file) {
                                        handleImport(file);
                                    }

                                    event.currentTarget.value = '';
                                }}
                            />
                            <Button
                                className="inline-flex flex-row items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                                onClick={() => fileInputRef.current?.click()}
                            >
                                <div className="flex flex-row items-center gap-1.5">
                                    <Upload className="h-4 w-4" />
                                    <span>Import</span>
                                </div>
                            </Button>
                            <Link href="/staff/uacs/create">
                                <Button className="inline-flex flex-row items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                    <div className="flex flex-row items-center gap-1.5">
                                        <Plus className="h-4 w-4 stroke-[2.5]" />
                                        <span>Add UACS</span>
                                    </div>
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                </Card>

                <div className="rounded-2xl">
                    <ClientDataTable
                        columns={columns}
                        data={uacs}
                        searchableColumns={[
                            'classification',
                            'sub_class',
                            'group',
                            'object_code',
                            'sub_object_code',
                            'uacs',
                            'status',
                        ]}
                        searchPlaceholder="Search for any values or status"
                        emptyMessage="No UACS records found"
                        filters={[
                            {
                                column: 'classification',
                                label: 'Classification',
                                options: Array.from(
                                    new Set(
                                        uacs
                                            .map((row) => row.classification)
                                            .filter((value): value is string =>
                                                Boolean(value),
                                            ),
                                    ),
                                ).map((value) => ({
                                    label: value,
                                    value,
                                })),
                                widthClassName: 'w-44',
                            },
                            {
                                column: 'sub_class',
                                label: 'Sub-Class',
                                options: Array.from(
                                    new Set(
                                        uacs
                                            .map((row) => row.sub_class)
                                            .filter((value): value is string =>
                                                Boolean(value),
                                            ),
                                    ),
                                ).map((value) => ({
                                    label: value,
                                    value,
                                })),
                                widthClassName: 'w-50',
                                contentWidthClassName: 'w-70',
                            },
                            {
                                column: 'status',
                                label: 'Status',
                                options: Array.from(
                                    new Set(
                                        uacs
                                            .map((row) => row.status)
                                            .filter((value): value is string =>
                                                Boolean(value),
                                            ),
                                    ),
                                ).map((value) => ({
                                    label: value,
                                    value,
                                })),
                                widthClassName: 'w-36',
                            },
                        ]}
                    />
                </div>
            </div>
        </>
    );
}
