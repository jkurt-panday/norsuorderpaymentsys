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
                className="flex w-full items-center justify-between rounded-md border border-[#CFE3FF] bg-white px-3 py-2 pr-14 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
            >
                <span
                    className={
                        selectedStudent
                            ? 'font-medium text-[#0B3D91]'
                            : 'text-[#7FA6D6]'
                    }
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
                        className="rounded p-0.5 text-[#8AA8CC] hover:text-[#0B3D91]"
                        aria-label="Clear selected student"
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
                <ChevronsUpDown className="h-4 w-4 text-[#7FA6D6]" />
            </div>

            {isOpen && (
                <div className="absolute z-50 mt-1 w-full space-y-2 rounded-md border border-[#CFE3FF] bg-white p-2 shadow-lg">
                    <div className="relative">
                        <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-[#8AA8CC]" />
                        <Input
                            type="text"
                            placeholder="Type Student ID or name to filter..."
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="h-9 border-[#CFE3FF] pl-8 text-xs"
                            autoFocus
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="absolute top-2.5 right-2.5 text-xs text-[#8AA8CC] hover:text-[#0B3D91]"
                                aria-label="Clear student search"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>

                    <div className="max-h-60 divide-y divide-[#EAF2FF] overflow-y-auto rounded-md border border-[#EAF2FF]">
                        {filteredStudents.length === 0 ? (
                            <p className="p-3 text-center text-xs text-[#8AA8CC]">
                                No students found.
                            </p>
                        ) : (
                            filteredStudents.map((student) => {
                                const isSelected =
                                    String(student.id) === String(value);

                                return (
                                    <div
                                        key={student.id}
                                        className={`flex items-center justify-between transition-colors hover:bg-[#F3F8FF] ${
                                            isSelected
                                                ? 'bg-[#EAF2FF] font-semibold text-[#0B3D91]'
                                                : 'text-[#334E68]'
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
                                                <Check className="ml-2 h-3.5 w-3.5 shrink-0 text-[#0F6FFF]" />
                                            )}
                                        </button>
                                        {onRemove && (
                                            <button
                                                type="button"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    onRemove(student.id);
                                                }}
                                                className="mr-2 rounded p-0.5 text-[#8AA8CC] hover:text-[#0B3D91]"
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
