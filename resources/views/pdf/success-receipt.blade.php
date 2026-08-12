<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $formInput->reference_number }}</title>
    @vite('resources/css/app.css')
    <style>
        body {
            font-family: Arial, sans-serif;
            background: white;
        }
    </style>
</head>
<body class="bg-gray-100 p-6">

    <div class="page-container bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200">

        <!-- Header Banner -->
        <div class="bg-gradient-to-b from-blue-500 to-blue-600 text-white text-center p-6 relative">
            <div class="flex items-center justify-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-full bg-yellow-500 border-2 border-white flex items-center justify-center text-xs font-bold text-gray-900 overflow-hidden">
                    <x-lucide-shield-check class="w-5 h-5 text-yellow-900" />
                </div>
                <h1 class="text-2xl font-black tracking-wider uppercase">FINANCE</h1>
            </div>

            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2 backdrop-blur-sm border border-white/30">
                <x-lucide-check class="w-7 h-7 text-white" />
            </div>

            <h2 class="text-xl font-bold">Submission Successful!</h2>
            <p class="text-xs text-blue-100 mt-1">Your request has been submitted successfully.</p>
        </div>

        <!-- Receipt Body Content -->
        <div class="p-6 space-y-6">

            <!-- Reference Number -->
            <div class="bg-blue-50/60 border border-blue-100 rounded-xl p-4 text-center">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Reference Number</p>
                <p class="text-2xl font-black text-blue-600 my-1">{{ $formInput->reference_number }}</p>
                <p class="text-[11px] text-gray-400">Please keep this reference number for tracking your request.</p>
            </div>

            <!-- Contact Information -->
            <div>
                <h3 class="text-xs font-bold text-blue-900 flex items-center gap-1.5 border-b border-gray-100 pb-2 mb-3">
                    <x-lucide-user class="w-4 h-4 text-blue-600" />
                    Contact Information
                </h3>
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="flex items-center gap-1 text-[10px] font-bold text-gray-400 uppercase">
                            <x-lucide-mail class="w-3 h-3 text-gray-400" />
                            Email
                        </span>
                        <span class="font-bold text-gray-800 break-all">{{ $formInput->email }}</span>
                    </div>
                    <div>
                        <span class="flex items-center gap-1 text-[10px] font-bold text-gray-400 uppercase">
                            <x-lucide-phone class="w-3 h-3 text-gray-400" />
                            Contact Number
                        </span>
                        <span class="font-bold text-gray-800">{{ $formInput->contact_num }}</span>
                    </div>
                </div>
            </div>

            <!-- Identity Details -->
            <div>
                <h3 class="text-xs font-bold text-blue-900 flex items-center gap-1.5 border-b border-gray-100 pb-2 mb-3">
                    <x-lucide-id-card class="w-4 h-4 text-blue-600" />
                    Identity Details
                </h3>
                <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-xs">
                    <div>
                        <span class="block text-[10px] font-bold text-gray-400 uppercase">First Name / Office</span>
                        <span class="font-bold text-gray-800">{{ $formInput->firstname_or_office }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-gray-400 uppercase">Middle Name / Project</span>
                        <span class="font-bold text-gray-800">{{ $formInput->middlename_or_project }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-gray-400 uppercase">Last Name / Agency</span>
                        <span class="font-bold text-gray-800">{{ $formInput->lastname_or_agency }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-gray-400 uppercase">Office / College</span>
                        <span class="font-bold text-gray-800">{{ $formInput->office_or_college }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-gray-400 uppercase">Position / Designation</span>
                        <span class="font-bold text-gray-800">{{ $formInput->position_or_designation }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="flex items-center gap-1 text-[10px] font-bold text-gray-400 uppercase">
                            <x-lucide-map-pin class="w-3 h-3 text-gray-400" />
                            Address
                        </span>
                        <span class="font-bold text-gray-800">{{ $formInput->address }}</span>
                    </div>
                </div>
            </div>

            <!-- Request Details -->
            <div>
                <h3 class="text-xs font-bold text-blue-900 flex items-center gap-1.5 border-b border-gray-100 pb-2 mb-3">
                    <x-lucide-receipt class="w-4 h-4 text-blue-600" />
                    Request Details
                </h3>
                <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-xs">
                    <div>
                        <span class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Request Type</span>
                        <span class="inline-block bg-emerald-100 text-emerald-700 px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                            {{ $formInput->request_type }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-gray-400 uppercase">Amount</span>
                        <span class="font-black text-blue-600 text-base">₱ {{ number_format($formInput->amount, 2) }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-gray-400 uppercase">Membership Type</span>
                        <span class="font-bold text-gray-800">{{ $formInput->membership->member_code ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-gray-400 uppercase">Payment Details</span>
                        <span class="font-bold text-gray-800">{{ $formInput->paymentDetailOption->payment_desc ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Supporting Documents -->
            <div>
                <h3 class="text-xs font-bold text-blue-900 flex items-center gap-1.5 border-b border-gray-100 pb-2 mb-3">
                    <x-lucide-file-text class="w-4 h-4 text-blue-600" />
                    Supporting Documents
                </h3>

                @forelse($formInput->supportingDocuments as $document)
                    <div class="bg-blue-50/70 border border-blue-100 rounded-lg p-3 flex items-center gap-3 mb-2">
                        <x-lucide-file class="w-5 h-5 text-gray-500 shrink-0" />
                        <div>
                            <p class="text-xs font-bold text-gray-800">{{ $document->original_filename ?? $document->file_path }}</p>
                            <p class="text-[10px] text-gray-400">Uploaded: {{ $document->created_at?->format('M d, Y') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-400 italic">No supporting documents uploaded.</p>
                @endforelse
            </div>

            <!-- Timestamp Footer -->
            <div class="text-center pt-2 border-t border-gray-100">
                <p class="text-[11px] text-gray-400">
                    Submitted on: {{ $formInput->created_at?->format('F d, Y \a\t h:i A') }}
                </p>
            </div>

        </div>
    </div>

</body>
</html>
