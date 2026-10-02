export const semesterOptions = [
    { label: '1st Sem.', value: 'First Semester' },
    { label: '2nd Sem.', value: 'Second Semester' },
    { label: 'Summer', value: 'Summer' },
] as const;

export const entryTypeOptions = [
    { value: 'ar', label: 'AR' },
    { value: 'payment', label: 'Payment' },
    { value: 'adjustment', label: 'Adjustment' },
] as const;

export const particularsOptions = [
    'Registration',
    'Tuition',
    'Miscellaneous',
    'Adjustment',
    'Payment',
] as const;

export type LawLedgerEntryType = (typeof entryTypeOptions)[number]['value'];
