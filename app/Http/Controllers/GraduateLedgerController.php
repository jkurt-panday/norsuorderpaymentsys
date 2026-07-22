<?php

namespace App\Http\Controllers;

use App\Models\GraduateLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GraduateLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = GraduateLedger::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                  ->orWhere('course', 'like', "%{$search}%")
                  ->orWhere('reference_or_jev_number', 'like', "%{$search}%");
            });
        }

        // Limit results to keep browser smooth (or page using ->paginate(100))
        $records = $query->orderBy('id', 'desc')->take(100)->get();

       $formattedRecords = $records->map(function ($item) {
    $rawType = strtoupper(trim($item->ar_payment ?? ''));

    // Determine category based on database string
    if ($rawType === 'PAYMENT' || $rawType === 'P') {
        $arPayment = 'Payment';
    } elseif ($rawType === 'ADJUSTMENT' || $rawType === 'ADJ') {
        $arPayment = 'Adjustment';
    } else {
        $arPayment = 'AR'; // Defaults to Account Receivable (Charge)
    }

    return [
        'id'              => $item->id,
        'name'            => $item->student_name ?? 'N/A',
        'course'          => $item->course ?? '',
        'schoolYear'      => $item->school_year ?? '',
        'term'            => $item->semester_short ?? $item->semester ?? '',
        'units'           => (int) ($item->units ?? 0),
        'transactionDate' => $item->transaction_date ? $item->transaction_date->format('Y-m-d') : '',
        'referenceNo'     => $item->reference_or_jev_number ?? '',
        'particulars'     => $item->particulars ?? '',
        'ratePerUnit'     => (float) $item->tuition_per_unit_or_misc,
        'amount'          => (float) $item->amount,
        'arPayment'       => $arPayment,
        'remark'          => $item->remarks ?? '',
        'inputBy'         => $item->input_by ?? '',
    ];
});
        // 🎯 Matches resources/js/pages/graduate-ledger/Index.tsx
return Inertia::render('graduate-ledger/Index', [
        'records' => $formattedRecords,
    ]);
    }
}