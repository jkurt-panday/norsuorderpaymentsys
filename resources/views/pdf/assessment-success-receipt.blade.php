<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        Assessment Receipt #{{ $assessmentForm->reference_number }}
    </title>
    @vite('resources/css/app.css')
    <style>
        body {
            font-family: 'Instrumental Sans', sans-serif;
            background: white;
        }
        @media print {
            body {
                background: white !important;
            }
            .print-hidden {
                display: none !important;
            }
            .print-card {
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="page-container mx-auto max-w-4xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
        <!-- Header -->
        <div class="relative rounded-b-2xl rounded-t-2xl bg-gradient-to-b from-blue-600 to-blue-400 px-6 py-8 text-center text-white">

            <!-- NORSU Logo -->
            <div class="mx-auto flex items-center justify-center">
                <img
                    src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('finance_logo1.png'))) }}"
                    alt="NORSU Logo"
                    style="width: 300px; height: auto;"
                >
            </div>

            <!-- Success Icon -->
            <div class="mx-auto mt-3 flex h-20 w-20 items-center justify-center rounded-full bg-white/15 backdrop-blur-sm border border-white/20">
                <x-lucide-circle-check class="h-12 w-12 text-white" />
            </div>

            <h2 class="mt-3 text-2xl font-bold text-green-300">
                Submission Successful!
            </h2>

            <p class="mt-1 text-sm text-blue-100">
                Your assessment request has been submitted successfully.
            </p>

        </div>


        <!-- Receipt Body -->
        <div class="space-y-6 p-6">


            <!-- Reference Number -->
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-5 text-center">

                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                    Reference Number
                </p>

                <p class="my-1 break-all font-mono text-2xl font-black tracking-wider text-blue-700">
                    {{ $assessmentForm->reference_number }}
                </p>

                <p class="text-[11px] text-gray-400">
                    Please keep this reference number for tracking your request.
                </p>

            </div>


            <!-- Contact Information -->
            <div>

                <h3 class="mb-3 flex items-center gap-1.5 border-b border-gray-100 pb-2 text-xs font-bold text-blue-900">

                    <x-lucide-user class="h-4 w-4 text-blue-600" />

                    Contact Information

                </h3>

                <div class="grid grid-cols-2 gap-4 text-xs">

                    <!-- Email -->
                    <div>

                        <span class="flex items-center gap-1 text-[10px] font-bold uppercase text-gray-400">

                            <x-lucide-mail class="h-3 w-3 text-gray-400" />

                            Email

                        </span>

                        <span class="break-all font-bold text-gray-800">
                            {{ $assessmentForm->email }}
                        </span>

                    </div>


                    <!-- Contact Number -->
                    <div>

                        <span class="flex items-center gap-1 text-[10px] font-bold uppercase text-gray-400">

                            <x-lucide-phone class="h-3 w-3 text-gray-400" />

                            Contact Number

                        </span>

                        <span class="font-bold text-gray-800">

                            @if ($assessmentForm->contact_num)
                                {{ substr($assessmentForm->contact_num, 0, 4) }}
                                {{ substr($assessmentForm->contact_num, 4, 3) }}
                                {{ substr($assessmentForm->contact_num, 7, 4) }}
                            @else
                                N/A
                            @endif

                        </span>

                    </div>

                </div>

            </div>


            <!-- Identity Details -->
            <div>

                <h3 class="mb-3 flex items-center gap-1.5 border-b border-gray-100 pb-2 text-xs font-bold text-blue-900">

                    <x-lucide-id-card class="h-4 w-4 text-blue-600" />

                    Identity Details

                </h3>


                <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-xs">

                    <!-- Full Name -->
                    <div class="col-span-2">

                        <span class="block text-[10px] font-bold uppercase text-gray-400">
                            Full Name
                        </span>

                        <span class="font-bold text-gray-800">

                            {{ $assessmentForm->last_name
                                ? $assessmentForm->last_name . ','
                                : ''
                            }}

                            {{ $assessmentForm->first_name }}

                            {{ $assessmentForm->middle_name }}

                        </span>

                    </div>

                    <!-- student id -->
                    <div class="col-span-2">

                        <span class="flex items-center gap-1 text-[10px] font-bold uppercase text-gray-400">

                            <x-lucide-hash class="h-3 w-3 text-gray-400" />

                            Student ID

                        </span>

                        <span class="font-bold text-gray-800">
                            {{ $assessmentForm->student_id }}
                        </span>

                    </div>

                    <!-- Address -->
                    <div class="col-span-2">

                        <span class="flex items-center gap-1 text-[10px] font-bold uppercase text-gray-400">

                            <x-lucide-map-pin class="h-3 w-3 text-gray-400" />

                            Address

                        </span>

                        <span class="font-bold text-gray-800">
                            {{ $assessmentForm->address }}
                        </span>

                    </div>


                    <!-- Course -->
                    <div class="col-span-2">

                        <span class="block text-[10px] font-bold uppercase text-gray-400">
                            Course
                        </span>

                        <span class="font-bold text-gray-800">
                            {{ $assessmentForm->course->course_desc ?? 'N/A' }}
                        </span>

                    </div>

                </div>

            </div>


            <!-- Assessment Details -->
            <div>

                <h3 class="mb-3 flex items-center gap-1.5 border-b border-gray-100 pb-2 text-xs font-bold text-blue-900">

                    <x-lucide-receipt class="h-4 w-4 text-blue-600" />

                    Assessment Details

                </h3>


                <div class="grid grid-cols-3 gap-x-4 gap-y-3 text-xs">


                    <!-- Enrolled Under -->
                    <div>

                        <span class="mb-1 block text-[10px] font-bold uppercase text-gray-400">
                            Enrolled Under
                        </span>

                        @php
                            $enrolledUnderClass = match ($assessmentForm->enrolled_under) {
                                'Undergraduate' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                                'Graduate' => 'bg-blue-100 text-blue-700 border border-blue-200',
                                'School of Law' => 'bg-amber-100 text-amber-700 border border-amber-200',
                                default => 'bg-slate-100 text-slate-700 border border-slate-200',
                            };
                        @endphp

                        <span class="{{ $enrolledUnderClass }} inline-block rounded-full px-3 py-1 font-bold">
                            {{ $assessmentForm->enrolled_under ?? 'N/A' }}
                        </span>

                    </div>


                    <!-- Semester -->
                    <div>

                        <span class="mb-1 block text-[10px] font-bold uppercase text-gray-400">
                            Semester
                        </span>

                        @php
                            $semesterClass = match ($assessmentForm->semester) {
                                'First Semester' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                                'Second Semester' => 'bg-blue-100 text-blue-700 border border-blue-200',
                                'Summer' => 'bg-amber-100 text-amber-700 border border-amber-200',
                                default => 'bg-slate-100 text-slate-700 border border-slate-200',
                            };
                        @endphp

                        <span class="{{ $semesterClass }} inline-block rounded-full px-3 py-1 font-bold">
                            {{ $assessmentForm->semester ?? 'N/A' }}
                        </span>

                    </div>


                    <!-- School Year -->
                    <div>

                        <span class="mb-1 block text-[10px] font-bold uppercase text-gray-400">
                            School Year Last Attended
                        </span>

                        <span class="inline-block rounded-full border border-slate-200 bg-slate-100 px-3 py-1 font-bold text-slate-700">
                            {{ $assessmentForm->sy_last_attended ?? 'N/A' }}
                        </span>

                    </div>

                </div>

            </div>


            <!-- Timestamp Footer -->
            <div class="border-t border-gray-100 pt-2 text-center">

                <p class="text-[11px] text-gray-400">

                    Submitted on:

                    {{ $assessmentForm->created_at?->setTimezone('Asia/Manila')->format('F d, Y \a\t h:i A') }}

                </p>

            </div>

        </div>

    </div>

</body>
</html>
