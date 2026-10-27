import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { columns } from './columns';
import type { UacsType } from './columns';
import { Button } from '@/components/base/buttons/button';
import { ClientDataTable } from '@/components/data-table/client-data-table';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';

type UacsProps = {
    uacs: UacsType[];
};

export default function UacsIndex({ uacs }: UacsProps) {
    return (
        <>
            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-page-background p-3 sm:p-6">
                <Head title="UACS" />

                <Card className="rounded-2xl border border-border bg-card px-2! py-2! text-card-foreground shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between p-3">
                        <CardTitle className="bg-[#3399FF] font-['Poppins'] bg-clip-text text-3xl font-black tracking-tight text-transparent sm:text-4xl">
                            UACS
                        </CardTitle>
                        <Link href="/staff/uacs/create">
                            <Button className="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                <div className="flex flex-row items-center gap-1.5">
                                    <Plus className="h-4 w-4 stroke-[2.5]" />
                                    <span>Add UACS</span>
                                </div>
                            </Button>
                        </Link>
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
                    />
                </div>
            </div>
        </>
    );
}
