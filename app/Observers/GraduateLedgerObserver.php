<?php

namespace App\Observers;

use App\Models\GraduateLedger;
use Illuminate\Support\Facades\Log;
use Revolution\Google\Sheets\Facades\Sheets;

class GraduateLedgerObserver
{
    /**
     * Handle the GraduateLedger "created" event (Appends new row to Google Sheet).
     */
    public function created(GraduateLedger $ledger): void
    {
        try {
            $spreadsheetId = env('GOOGLE_SPREADSHEET_ID');

            if (blank($spreadsheetId)) {
                return;
            }

            $row = [
                $ledger->student_name,
                $ledger->course,
                $ledger->school_year,
                $ledger->semester_short ?: $ledger->semester,
                $ledger->units,
                $ledger->transaction_date,
                $ledger->reference_or_jev_number,
                $ledger->particulars,
                $ledger->tuition_per_unit_or_misc,
                $ledger->ar_payment,
                $ledger->amount,
                $ledger->remarks,
                $ledger->input_by,
            ];

            // Appends new row to Sheet 1 (or specify sheet name like 'Graduate School')
            Sheets::spreadsheet($spreadsheetId)
                ->sheet('Graduate School')
                ->append([$row]);

        } catch (\Exception $e) {
            Log::error('Google Sheet Sync Error on Create: '.$e->getMessage());
        }
    }
}
