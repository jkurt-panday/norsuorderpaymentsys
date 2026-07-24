<?php

namespace App\Console\Commands;

use App\Models\GraduateLedger;
use Illuminate\Console\Command;

class SyncLedgerToGoogleSheets extends Command
{
    protected $signature = 'ledger:sync-sheets';

    protected $description = 'Pushes all records from DB to Google Sheets';

    public function handle()
    {
        $this->info('Fetching records from database...');
        $records = GraduateLedger::orderBy('id', 'asc')->get();

        $rows = [
            [
                'NAME (Last Name, First Name, M.I.)',
                'COURSE',
                'SCHOOL YEAR',
                'SEMESTER/SUMMER',
                'UNITS',
                'TRANSACTION DATE',
                'Reference JEV / O.R. NUMBER',
                'PARTICULARS',
                'TUITION per UNIT/ Reg. and Miscellaneous per semester',
                'AR/PAYMENT',
                'AMOUNT',
                'REMARKS',
                'INPUT BY:',
            ],
        ];

        foreach ($records as $r) {
            $rows[] = [
                $r->student_name,
                $r->course,
                $r->school_year,
                $r->semester_short ?: $r->semester,
                $r->units,
                $r->transaction_date,
                $r->reference_or_jev_number,
                $r->particulars,
                $r->tuition_per_unit_or_misc,
                $r->ar_payment,
                $r->amount,
                $r->remarks,
                $r->input_by,
            ];
        }

        $spreadsheetId = config('sheets.post_spreadsheet_id') ?? env('GOOGLE_SPREADSHEET_ID');

        // Access the registered service instance safely
        $sheetsService = app('sheets');

        $this->info('Clearing old Google Sheet data...');
        $sheetsService->spreadsheet($spreadsheetId)->sheet('Graduate School')->clear();

        $this->info('Uploading '.count($rows).' rows to Google Sheets...');
        foreach (array_chunk($rows, 1000) as $chunk) {
            $sheetsService->spreadsheet($spreadsheetId)->sheet('Graduate School')->append($chunk);
        }

        $this->info('Successfully synced to Google Sheets!');
    }
}
