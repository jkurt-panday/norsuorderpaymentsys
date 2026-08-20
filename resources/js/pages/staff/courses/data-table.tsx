import { useTable, type ColumnDef, type RowData } from '@tanstack/react-table';
import { features, type DataTableFeatures } from './data-table-features';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import type { Course } from './columns';

interface DataTableProps<TData extends RowData> {
    columns: ColumnDef<DataTableFeatures, TData>[];
    data: TData[];
}

export function DataTable<TData extends RowData>({
    columns,
    data,
}: DataTableProps<TData>) {
    const table = useTable({
        features,
        data,
        columns,
    });

    return (
        <div className="overflow-hidden border">
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id}>
                            {headerGroup.headers.map((header) => (
                                <TableHead key={header.id}>
                                    {header.isPlaceholder ? null : (
                                        <table.FlexRender header={header} />
                                    )}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>

                <TableBody>
                    {table.getRowModel().rows.length > 0 ? (
                        table.getRowModel().rows.map((row) => (
                            <TableRow key={row.id}>
                                {row.getAllCells().map((cell) => (
                                    <TableCell key={cell.id}>
                                        <table.FlexRender cell={cell} />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell
                                colSpan={columns.length}
                                className="h-24 text-center"
                            >
                                No courses found.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
