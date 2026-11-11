export type CohortEntryType = 'ar' | 'payment' | 'adjustment';

export interface CohortStudentOption {
    id: number | string;
    student_number?: string | null;
    last_name: string;
    first_name: string;
    middle_name?: string | null;
    last_course_id?: number | string | null;
    course_id?: number | string | null;
    email?: string | null;
}

export interface CohortCourseOption {
    id: number | string;
    code: string;
    name?: string;
    course_name?: string;
}

export interface CohortAcademicTermOption {
    id: number;
    school_year: string;
    semester: string;
}

export interface CohortRowOverride {
    student_id: number;
    student_name: string;
    student_number: string;
    units: number;
    rate: number;
    amount: number;
    membership?: 'NAPU' | 'NORSUFFA' | '' | null;
    latin_honor?: 'SUMMA' | 'MAGNA' | 'CUM_LAUDE' | '' | null;
    discount_amount?: number;
    remarks?: string;
}

export interface CohortTemplate {
    course_id: number | string;
    academic_term_id: number | string;
    school_year: string;
    semester: string;
    entry_type: CohortEntryType;
    transaction_date: string;
    particulars: string;
    reference_or_jev_number: string;
    units: string;
    rate: string;
    amount: string;
    membership?: 'NAPU' | 'NORSUFFA' | '';
    latin_honor?: 'SUMMA' | 'MAGNA' | 'CUM_LAUDE' | '';
    remarks?: string;
}
