import PublicLayout from '@/pages/layouts/PublicLayout';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { useState } from 'react';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';

const reqType = ['New Request', 'Re-issue Request', 'Other'] as const;

const memId = ['NORSUFFA', 'NAPU', 'Not Applicable'] as const;

const paymentDet = [
    'Comprehensive Exam - Doctorate',
    'Comprehensive Exam - Masters',
    'IGP FABLAB',
    'IGP Royalty Fee of NORSU Logo',
    'IGP Sale of Agricultural Produce',
    'IGP Sale of Beverages and Meals',
    'IGP Sale of Bid Docs',
] as const;

export default function SubmitForm() {
    // functions
    const [value, setValue] = useState('');

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;

        // Allows only non-negative numbers with up to 2 decimal places
        if (val === '' || /^\d*\.?\d{0,2}$/.test(val)) {
            setValue(val);
        }
    };

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle className="text-2xl">
                        Contact Information
                    </CardTitle>
                    <CardDescription>
                        Fill out the details below
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-6">
                    {/* Section 1: Contact Information */}
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                {/* email add */}
                                <Field>
                                    <FieldLabel htmlFor="input-field-email">
                                        Email Address
                                    </FieldLabel>
                                    <Input
                                        id="input-field-email"
                                        type="email"
                                        placeholder="jondoe@mail.com"
                                    />
                                </Field>
                            </div>
                            <div className="space-y-2">
                                {/* contact num */}
                                <Field>
                                    <FieldLabel htmlFor="input-field-contact">
                                        Contact Num
                                    </FieldLabel>
                                    <Input
                                        id="input-field-contact"
                                        type="tel"
                                        placeholder="0912 333 4444"
                                        maxLength={11}
                                        minLength={11}
                                    />
                                </Field>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <CardTitle className="text-2xl">Identity Details</CardTitle>

                    {/* Section 2: Additional Info */}
                    <div className="space-y-4">
                        {/* name sections */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-3">
                            <div className="space-y-2">
                                {/* first name / office */}
                                <Field>
                                    <FieldLabel htmlFor="input-field-fname">
                                        First Name / Office
                                    </FieldLabel>
                                    <Input id="input-field-fname" type="text" />
                                </Field>
                            </div>
                            <div className="space-y-2">
                                {/* middle name / project */}
                                <Field>
                                    <FieldLabel htmlFor="input-field-mname">
                                        Middle Name / Project
                                    </FieldLabel>
                                    <Input id="input-field-mname" type="text" />
                                </Field>
                            </div>
                            <div className="space-y-2">
                                {/* last name / agency */}
                                <Field>
                                    <FieldLabel htmlFor="input-field-lname">
                                        Last Name / Agency
                                    </FieldLabel>
                                    <Input id="input-field-lname" type="text" />
                                </Field>
                            </div>
                        </div>

                        {/* designation section */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-2">
                            <div className="space-y-2">
                                {/* office / college */}
                                <Field>
                                    <FieldLabel htmlFor="input-field-off-coll">
                                        Office / College
                                    </FieldLabel>
                                    <Input
                                        id="input-field-off-coll"
                                        type="text"
                                    />
                                </Field>
                            </div>
                            <div className="space-y-2">
                                {/* position / designation */}
                                <Field>
                                    <FieldLabel htmlFor="input-field-pos-des">
                                        Position / Designation
                                    </FieldLabel>
                                    <Input
                                        id="input-field-pos-des"
                                        type="text"
                                    />
                                </Field>
                            </div>
                        </div>
                    </div>
                    <div className="space-y-4">
                        {/* address */}
                        <Field>
                            <FieldLabel htmlFor="input-field-address">
                                Address
                            </FieldLabel>
                            <Input id="input-field-address" type="text" />
                        </Field>
                    </div>

                    <Separator />

                    <CardTitle className="text-2xl">Request Details</CardTitle>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-2">
                        {/* amount */}
                        <Field>
                            <FieldLabel htmlFor="input-field-amount">
                                Amount
                            </FieldLabel>
                            <div className="flex">
                                <div className="rounded-l-md border border-r-0 bg-muted px-3 py-1.5 text-sm text-muted-foreground">
                                    &#x20B1;
                                </div>
                                <Input
                                    id="input-field-amount"
                                    type="text"
                                    inputMode="decimal"
                                    placeholder="00.00"
                                    value={value}
                                    onChange={handleChange}
                                    className="rounded-l-none"
                                />
                            </div>
                        </Field>
                        {/* request type */}
                        <Field>
                            <FieldLabel htmlFor="input-field-reqtype">
                                Request Type
                            </FieldLabel>
                            <Combobox items={reqType}>
                                <ComboboxInput
                                    placeholder="Select a request type"
                                    showClear
                                />
                                <ComboboxContent>
                                    <ComboboxEmpty>
                                        No items found.
                                    </ComboboxEmpty>
                                    <ComboboxList>
                                        {(item) => (
                                            <ComboboxItem
                                                key={item}
                                                value={item}
                                            >
                                                {item}
                                            </ComboboxItem>
                                        )}
                                    </ComboboxList>
                                </ComboboxContent>
                            </Combobox>
                        </Field>
                        {/* membership type */}
                        <Field>
                            <FieldLabel htmlFor="input-field-memtype">
                                Membership Type
                            </FieldLabel>
                            <Combobox items={memId}>
                                <ComboboxInput
                                    placeholder="Select membership"
                                    showClear
                                />
                                <ComboboxContent>
                                    <ComboboxEmpty>
                                        No items found.
                                    </ComboboxEmpty>
                                    <ComboboxList>
                                        {(item) => (
                                            <ComboboxItem
                                                key={item}
                                                value={item}
                                            >
                                                {item}
                                            </ComboboxItem>
                                        )}
                                    </ComboboxList>
                                </ComboboxContent>
                            </Combobox>
                        </Field>

                        {/* payment details */}
                        <Field>
                            <FieldLabel htmlFor="input-field-paydet">
                                Payment Details
                            </FieldLabel>
                            <Combobox items={paymentDet}>
                                <ComboboxInput
                                    placeholder="Select payment details"
                                    showClear
                                />
                                <ComboboxContent>
                                    <ComboboxEmpty>
                                        No items found.
                                    </ComboboxEmpty>
                                    <ComboboxList>
                                        {(item) => (
                                            <ComboboxItem
                                                key={item}
                                                value={item}
                                            >
                                                {item}
                                            </ComboboxItem>
                                        )}
                                    </ComboboxList>
                                </ComboboxContent>
                            </Combobox>
                        </Field>
                    </div>

                    <Separator />

                    <CardTitle className="text-2xl">
                        Supporting Documents
                    </CardTitle>
                </CardContent>

                <CardFooter className="flex justify-end gap-2 pt-25">
                    <Button variant="outline">Cancel</Button>
                    <Button>Submit Request</Button>
                </CardFooter>
            </Card>
        </>
    );
}

SubmitForm.layout = (page: React.ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
