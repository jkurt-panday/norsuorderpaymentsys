import { useForm, Head } from '@inertiajs/react';
import PublicLayout from '@/pages/layouts/PublicLayout';
import { Mail, User, University } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from '@/components/ui/card';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldLabel,
    FieldTitle,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';

const enlarge =
    'h-12 rounded-xl border-slate-300 bg-white px-4 text-base shadow-sm transition-all duration-200';

// Generates array of academic years: ["2026-2027", "2025-2026", "2024-2025", ...]
export const SCHOOL_YEAR_OPTIONS = (() => {
    const today = new Date();
    const currentYear = today.getFullYear();
    const currentMonth = today.getMonth(); // 0 = Jan, 7 = August

    // If before August (month < 7), academic start year is Year - 1
    const startAcademicYear = currentMonth >= 7 ? currentYear : currentYear - 1;

    const years: string[] = [];
    for (let i = 0; i < 27; i++) {
        const startYear = startAcademicYear - i;
        years.push(`SY ${startYear}-${startYear + 1}`);
    }
    return years;
})();

export enum EnrolledUnder {
    UNDERGRADUATE = 'Undergraduate',
    GRADUATE = 'Graduate',
    LAW = 'School of Law',
}

export enum Semester {
    FIRST = 'First Semester',
    SECOND = 'Second Semester',
    SUMMER = 'Summer',
}

interface Course {
    id: number | string;
    course_code: string;
    course_desc: string;
}

interface Props {
    courses: Course[];
}

export default function AssessmentForm({ courses }: Props) {
    // ? form handling
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        contact_num: '',
        first_name: '',
        middle_name: '',
        last_name: '',
        course_id: '',
        student_id: '',
        address: '',
        enrolled_under: EnrolledUnder.UNDERGRADUATE,
        sy_last_attended: '',
        semester: Semester.FIRST,
    });

    const handleSubmit = (e: React.SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();

        post('/public/assessmentform', {
            forceFormData: true,
            preserveState: true,
            onSuccess: () => console.log('Success'),
            onError: (errs) => console.error('Validation errors: ', errs)
        })
    };

    // contact num length
    const isValidContact = data.contact_num.length === 11;

    return (
        <>
            <Head title="Assessment Form" />
            <div className="min-h-screen bg-linear-to-b from-blue-500 via-slate-200 to-white px-6 py-12">
                <div className="mx-auto max-w-4xl">
                    <div className="mb-10 rounded-2xl border border-blue-100 bg-white px-8 py-8 text-center shadow-md">
                        <div className="flex justify-center">
                            <img
                                src="/finance_logo1.png"
                                alt="NORSU Logo"
                                width={500}
                                height={500}
                                className="pb-6"
                            />
                        </div>
                        <h1 className="text-4xl font-bold tracking-tight text-blue-900">
                            Assessment Form - Main Campus 1
                        </h1>
                        <p className="mt-3 text-base text-slate-600">
                            For Undergraduate (not covered under RA 10931-FHE),
                            Graduate School and School of Law students only
                        </p>
                    </div>
                    {/* card */}
                    <form noValidate onSubmit={handleSubmit}>
                        <Card className="overflow-hidden rounded-3xl border border-blue-100 bg-white shadow-xl">
                            <CardHeader className="border-b border-slate-100 bg-white pb-6">
                                <CardTitle className="flex items-center gap-3 text-xl font-semibold text-blue-900">
                                    <Mail className="h-5 w-5 text-blue-600" />
                                    Contact Information
                                </CardTitle>
                                <CardDescription>
                                    Fill out the details below
                                </CardDescription>
                                {/*<pre>{JSON.stringify(errors, null, 2)}</pre>*/}
                            </CardHeader>

                            {/* card content */}
                            <CardContent className="space-y-6">
                                {/* Section 1: Contact Information */}
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            {/* email add */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-email"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Email Address
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-email"
                                                    type="email"
                                                    placeholder="jondoe@mail.com"
                                                    value={data.email}
                                                    onChange={(e) =>
                                                        setData(
                                                            'email',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.email && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {errors.email}
                                                    </p>
                                                )}{' '}
                                            </Field>
                                        </div>
                                        <div className="space-y-2">
                                            {/* contact num */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-contact"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Contact Number
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-contact"
                                                    type="tel"
                                                    placeholder="0912 333 4444"
                                                    maxLength={13} // 11 digits + 2 spaces
                                                    value={data.contact_num.replace(
                                                        /^(\d{0,4})(\d{0,3})(\d{0,4}).*/,
                                                        (_, a, b, c) =>
                                                            [a, b, c]
                                                                .filter(Boolean)
                                                                .join(' '),
                                                    )}
                                                    onChange={(e) => {
                                                        const digits =
                                                            e.target.value
                                                                .replace(
                                                                    /\D/g,
                                                                    '',
                                                                )
                                                                .slice(0, 11);

                                                        setData(
                                                            'contact_num',
                                                            digits,
                                                        );
                                                    }}
                                                />
                                                {errors.contact_num && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {errors.contact_num}
                                                    </p>
                                                )}
                                                {data.contact_num.length > 0 &&
                                                    !isValidContact && (
                                                        <p className="mt-1 text-sm text-red-500">
                                                            Contact number must
                                                            contain exactly 11
                                                            digits.
                                                        </p>
                                                    )}
                                            </Field>
                                        </div>
                                    </div>
                                </div>
                                <Separator className="bg-blue-100" />
                                {/* identity details */}
                                <CardTitle className="flex items-center gap-3 text-xl font-semibold text-blue-900">
                                    <User className="h-5 w-5 text-blue-600" />
                                    Identity Details
                                </CardTitle>
                                <div className="space-y-4">
                                    {/* name sections */}
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-3">
                                        <div className="space-y-2">
                                            {/* first name */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-fname"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    First Name
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-fname"
                                                    type="text"
                                                    placeholder="Juan"
                                                    value={data.first_name}
                                                    onChange={(e) =>
                                                        setData(
                                                            'first_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.first_name && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {errors.first_name}
                                                    </p>
                                                )}
                                            </Field>
                                        </div>
                                        <div className="space-y-2">
                                            {/* middle name */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-mname"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Middle Name
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-mname"
                                                    type="text"
                                                    placeholder="Carlos"
                                                    value={data.middle_name}
                                                    onChange={(e) =>
                                                        setData(
                                                            'middle_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.middle_name && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {errors.middle_name}
                                                    </p>
                                                )}
                                            </Field>
                                        </div>
                                        <div className="space-y-2">
                                            {/* last name */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-lname"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Last Name
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-lname"
                                                    type="text"
                                                    placeholder="Dela Cruz"
                                                    value={data.last_name}
                                                    onChange={(e) =>
                                                        setData(
                                                            'last_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.last_name && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {errors.last_name}
                                                    </p>
                                                )}
                                            </Field>
                                        </div>
                                    </div>

                                    {/* course and address section */}
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-1 lg:grid-cols-2">
                                        {/* student id */}
                                        <Field>
                                            <FieldLabel
                                                htmlFor="input-field-studentId"
                                                className="mb-2 font-medium text-slate-700"
                                            >
                                                Student ID
                                            </FieldLabel>
                                            <Input
                                                className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none`}
                                                id="input-field-fname"
                                                type="number"
                                                placeholder="202300000"
                                                value={data.student_id}
                                                onChange={(e) =>{
                                                    const value = e.target.value.slice(0, 9); // Enforce max 9 digits
                                                    setData('student_id', value);
                                                }
                                                }
                                            />
                                            {errors.student_id && (
                                                <p className="mt-1 text-sm text-red-500">
                                                    {errors.student_id}
                                                </p>
                                            )}
                                        </Field>
                                        {/* courses */}
                                        <Field>
                                            <FieldLabel
                                                htmlFor="input-field-courses"
                                                className="mb-2 font-medium text-slate-700"
                                            >
                                                Course
                                            </FieldLabel>
                                            <Combobox
                                                required
                                                items={courses}
                                                value={
                                                    courses.find(
                                                        (m) =>
                                                            String(m.id) ===
                                                            data.course_id,
                                                    )?.course_desc || ''
                                                }
                                                onValueChange={(value) => {
                                                    const selected =
                                                        courses.find(
                                                            (m) =>
                                                                m.course_desc ===
                                                                value,
                                                        );

                                                    setData(
                                                        'course_id',
                                                        selected
                                                            ? String(
                                                                  selected.id,
                                                              )
                                                            : '',
                                                    );
                                                }}
                                            >
                                                <ComboboxInput
                                                    placeholder="Select course"
                                                    className={`border-slate-300 focus-within:border-blue-600! focus-within:ring-2! focus-within:ring-blue-600/30! data-[state=open]:border-blue-600! data-[state=open]:ring-2! data-[state=open]:ring-blue-600/30! data-open:border-blue-600! data-open:ring-2! data-open:ring-blue-600/30! ${enlarge}`}
                                                    showClear={
                                                        !!data.course_id
                                                    }
                                                />
                                                <ComboboxContent>
                                                    <ComboboxEmpty>
                                                        No items found.
                                                    </ComboboxEmpty>
                                                    <ComboboxList>
                                                        {(item) => (
                                                            <ComboboxItem
                                                                key={item.id}
                                                                value={
                                                                    item.course_desc
                                                                }
                                                            >
                                                                {
                                                                    item.course_desc
                                                                }
                                                            </ComboboxItem>
                                                        )}
                                                    </ComboboxList>
                                                </ComboboxContent>
                                            </Combobox>
                                            {errors.course_id && (
                                                <p className="mt-1 text-sm text-red-500">
                                                    {errors.course_id}
                                                </p>
                                            )}
                                        </Field>
                                    </div>
                                    <div className="space-y-4">
                                        {/* address */}
                                        <Field>
                                            <FieldLabel
                                                htmlFor="input-field-address"
                                                className="mb-2 font-medium text-slate-700"
                                            >
                                                Address
                                            </FieldLabel>
                                            <Input
                                                className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                id="input-field-address"
                                                type="text"
                                                placeholder="Kagawasan Ave. Dumaguete City"
                                                value={data.address}
                                                onChange={(e) =>
                                                    setData(
                                                        'address',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {errors.address && (
                                                <p className="mt-1 text-sm text-red-500">
                                                    {errors.address}
                                                </p>
                                            )}
                                        </Field>
                                    </div>
                                </div>
                                <Separator className="bg-blue-100" />
                                {/* academic details */}
                                <CardTitle className="flex items-center gap-3 text-xl font-semibold text-blue-900">
                                    <University className="h-5 w-5 text-blue-600" />
                                    Academic Details
                                </CardTitle>
                                <FieldLabel
                                    htmlFor="input-field-courses"
                                    className="mb-2 text-lg font-medium text-blue-500"
                                >
                                    Enrolled Under:
                                </FieldLabel>
                                <RadioGroup
                                    value={data.enrolled_under}
                                    onValueChange={(val) =>
                                        setData('enrolled_under', val)
                                    }
                                    className="grid w-full grid-cols-1 gap-4 sm:grid-cols-3"
                                >
                                    {/* 1. Undergraduate */}
                                    <FieldLabel htmlFor="enrolled-undergraduate">
                                        <Field
                                            orientation="horizontal"
                                            className="h-full cursor-pointer justify-between"
                                        >
                                            <FieldContent>
                                                <FieldTitle>
                                                    Undergraduate
                                                </FieldTitle>
                                                <FieldDescription>
                                                    (not covered under RA 10931
                                                    - Free Higher Education)
                                                </FieldDescription>
                                            </FieldContent>
                                            <RadioGroupItem
                                                value="Undergraduate"
                                                id="enrolled-undergraduate"
                                            />
                                        </Field>
                                    </FieldLabel>

                                    {/* 2. Graduate */}
                                    <FieldLabel htmlFor="enrolled-graduate">
                                        <Field
                                            orientation="horizontal"
                                            className="h-full cursor-pointer justify-between"
                                        >
                                            <FieldContent>
                                                <FieldTitle>
                                                    Graduate
                                                </FieldTitle>
                                                <FieldDescription>
                                                    For master's and doctoral
                                                    degree students.
                                                </FieldDescription>
                                            </FieldContent>
                                            <RadioGroupItem
                                                value="Graduate"
                                                id="enrolled-graduate"
                                            />
                                        </Field>
                                    </FieldLabel>

                                    {/* 3. School of Law */}
                                    <FieldLabel htmlFor="enrolled-law">
                                        <Field
                                            orientation="horizontal"
                                            className="h-full cursor-pointer justify-between"
                                        >
                                            <FieldContent>
                                                <FieldTitle>
                                                    School of Law
                                                </FieldTitle>
                                                <FieldDescription>
                                                    For Juris Doctor and legal
                                                    studies students.
                                                </FieldDescription>
                                            </FieldContent>
                                            <RadioGroupItem
                                                value="School of Law"
                                                id="enrolled-law"
                                            />
                                        </Field>
                                    </FieldLabel>
                                </RadioGroup>
                                {/* Validation Error Display */}
                                {errors.enrolled_under && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.enrolled_under}
                                    </p>
                                )}

                                {/* semester */}
                                <FieldLabel
                                    htmlFor="input-field-courses"
                                    className="mb-2 text-lg font-medium text-blue-500"
                                >
                                    Semester:
                                </FieldLabel>
                                <RadioGroup
                                    value={data.semester}
                                    onValueChange={(val) =>
                                        setData('semester', val)
                                    }
                                    className="grid w-full grid-cols-1 gap-4 sm:grid-cols-3"
                                >
                                    {/* 1. First Semester */}
                                    <FieldLabel htmlFor="sem-first">
                                        <Field
                                            orientation="horizontal"
                                            className="h-full cursor-pointer justify-between"
                                        >
                                            <FieldContent>
                                                <FieldTitle>
                                                    First Semester
                                                </FieldTitle>
                                            </FieldContent>
                                            <RadioGroupItem
                                                value="First Semester"
                                                id="sem-first"
                                            />
                                        </Field>
                                    </FieldLabel>

                                    {/* 2. Second Semester */}
                                    <FieldLabel htmlFor="sem-second">
                                        <Field
                                            orientation="horizontal"
                                            className="h-full cursor-pointer justify-between"
                                        >
                                            <FieldContent>
                                                <FieldTitle>
                                                    Second Semester
                                                </FieldTitle>
                                            </FieldContent>
                                            <RadioGroupItem
                                                value="Second Semester"
                                                id="sem-second"
                                            />
                                        </Field>
                                    </FieldLabel>

                                    {/* 3. Summer */}
                                    <FieldLabel htmlFor="sem-summer">
                                        <Field
                                            orientation="horizontal"
                                            className="h-full cursor-pointer justify-between"
                                        >
                                            <FieldContent>
                                                <FieldTitle>Summer</FieldTitle>
                                            </FieldContent>
                                            <RadioGroupItem
                                                value="Summer"
                                                id="sem-summer"
                                            />
                                        </Field>
                                    </FieldLabel>
                                </RadioGroup>

                                {/* School year */}
                                <FieldLabel
                                    htmlFor="input-field-courses"
                                    className="mb-2 text-lg font-medium text-blue-500"
                                >
                                    School-Year last attended:
                                </FieldLabel>
                                <Combobox
                                    items={SCHOOL_YEAR_OPTIONS}
                                    value={data.sy_last_attended}
                                    onValueChange={(val) =>
                                        setData('sy_last_attended', val || '')
                                    }
                                >
                                    <ComboboxInput
                                        placeholder="Select School Year"
                                        className={`border-slate-300 focus-within:border-blue-600! focus-within:ring-2! focus-within:ring-blue-600/30! data-[state=open]:border-blue-600! data-[state=open]:ring-2! data-[state=open]:ring-blue-600/30! data-open:border-blue-600! data-open:ring-2! data-open:ring-blue-600/30! ${enlarge}`}
                                        showClear={!!data.sy_last_attended}
                                    />
                                    <ComboboxContent>
                                        <ComboboxEmpty>
                                            No school year found.
                                        </ComboboxEmpty>
                                        <ComboboxList>
                                            {(sy) => (
                                                <ComboboxItem
                                                    key={sy}
                                                    value={sy}
                                                >
                                                    {sy}
                                                </ComboboxItem>
                                            )}
                                        </ComboboxList>
                                    </ComboboxContent>
                                </Combobox>

                                {/* Validation Error Display */}
                                {errors.sy_last_attended && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.sy_last_attended}
                                    </p>
                                )}
                                {/* end of the card*/}
                            </CardContent>

                            <CardFooter className="flex items-center justify-end gap-3 border-t border-slate-100 px-8 py-6 pt-4">
                                {/* Reset Form Button */}
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() => {
                                        reset();
                                        // Uncomment if clearing local state attachments:
                                        // setSupportingDocuments([]);
                                    }}
                                    className="cursor-pointer rounded-xl border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                                >
                                    Reset
                                </Button>
                            
                                {/* Submit Assessment Button */}
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="cursor-pointer rounded-xl bg-blue-600 font-semibold text-white shadow-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-600/30 disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    {processing ? (
                                        <span className="flex items-center gap-2">
                                            <svg
                                                className="h-4 w-4 animate-spin text-white"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle
                                                    className="opacity-25"
                                                    cx="12"
                                                    cy="12"
                                                    r="10"
                                                    stroke="currentColor"
                                                    strokeWidth="4"
                                                />
                                                <path
                                                    className="opacity-75"
                                                    fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                />
                                            </svg>
                                            Submitting...
                                        </span>
                                    ) : (
                                        'Submit Assessment'
                                    )}
                                </Button>
                            </CardFooter>
                        </Card>
                    </form>
                </div>
            </div>
        </>
    );
}

AssessmentForm.layout = (page: React.ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
