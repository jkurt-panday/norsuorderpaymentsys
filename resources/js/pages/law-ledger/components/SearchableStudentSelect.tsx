import { Check, ChevronsUpDown, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';

export interface StudentOption {
    id: number | string;
    student_number?: string | null;
    last_name: string;
    first_name: string;
    middle_name?: string | null;
    last_course_id?: number | string | null;
}

export function formatStudentLabel(student: StudentOption): string {
    const studentName = !student.first_name
        ? String(student.last_name)
        : `${student.last_name}, ${student.first_name}${student.middle_name ? ` ${student.middle_name.charAt(0).toUpperCase()}.` : ''}`;

    if (student.student_number) {
        return `${student.student_number} — ${studentName}`;
    }

    return studentName;
}

interface SearchableStudentSelectProps {
    students: StudentOption[];
    value: string | number;
    onChange: (id: string | number) => void;
    onClear?: () => void;
    onRemove?: (id: string | number) => void;
    placeholder?: string;
    inputId?: string;
}

export default function SearchableStudentSelect({
    students,
    value,
    onChange,
    onClear,
    onRemove,
    placeholder = '-- Select / Search Student --',
    inputId,
}: SearchableStudentSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');

    const selectedStudent = students.find(
        (student) => String(student.id) === String(value),
    );

    const filteredStudents = useMemo(() => {
        const query = search.toLowerCase().trim();

        if (!query) {
            return students.slice(0, 80);
        }

        return students
            .filter((student) => {
                const label = formatStudentLabel(student).toLowerCase();

                return label.includes(query);
            })
            .slice(0, 80);
    }, [students, search]);

    return (
        <div className="relative w-full">
            <button
                id={inputId}
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 pr-14 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
            >
                <span
                    className={selectedStudent ? 'font-medium text-foreground' : 'text-muted-foreground'}
                >
                    {selectedStudent
                        ? formatStudentLabel(selectedStudent)
                        : placeholder}
                </span>
            </button>

            <div className="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1">
                {selectedStudent && onClear && (
                    <button
                        type="button"
                        onClick={(event) => {
                            event.stopPropagation();
                            onClear();
                        }}
                        className="rounded p-0.5 text-muted-foreground hover:text-foreground"
                        aria-label="Clear selected student"
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
                <ChevronsUpDown className="h-4 w-4 text-muted-foreground" />
            </div>

            {isOpen && (
                <div className="absolute z-50 mt-1 w-full space-y-2 rounded-md border border-border bg-popover p-2 text-popover-foreground shadow-lg">
                    <div className="relative">
                        <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Type Student ID or name to filter..."
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="h-9 border-input bg-background pl-8 text-xs text-foreground"
                            autoFocus
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="absolute top-2.5 right-2.5 text-xs text-muted-foreground hover:text-foreground"
                                aria-label="Clear student search"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>

                    <div className="max-h-60 divide-y divide-border overflow-y-auto rounded-md border border-border">
                        {filteredStudents.length === 0 ? (
                            <p className="p-3 text-center text-xs text-muted-foreground">
                                No students found.
                            </p>
                        ) : (
                            filteredStudents.map((student) => {
                                const isSelected =
                                    String(student.id) === String(value);

                                return (
                                    <div
                                        key={student.id}
                                        className={`flex items-center justify-between transition-colors hover:bg-muted/40 ${
                                            isSelected
                                                ? 'bg-primary/10 font-semibold text-primary'
                                                : 'text-foreground'
                                        }`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => {
                                                onChange(student.id);
                                                setIsOpen(false);
                                            }}
                                            className="flex min-w-0 flex-1 items-center justify-between px-3 py-2 text-left text-xs"
                                        >
                                            <span className="truncate">
                                                {formatStudentLabel(student)}
                                            </span>
                                            {isSelected && (
                                                <Check className="ml-2 h-3.5 w-3.5 shrink-0 text-primary" />
                                            )}
                                        </button>
                                        {onRemove && (
                                            <button
                                                type="button"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    onRemove(student.id);
                                                }}
                                                className="mr-2 rounded p-0.5 text-muted-foreground hover:text-foreground"
                                                aria-label={`Remove ${formatStudentLabel(student)}`}
                                            >
                                                <X className="h-3.5 w-3.5" />
                                            </button>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
