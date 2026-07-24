<?php

namespace App\Console\Commands;

use App\Models\GraduateLedger;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportGraduateLedgerCsv extends Command
{
    protected $signature = 'ledger:import-csv
                            {file : Path to the CSV file (relative to project root or absolute)}
                            {--fresh : Truncate the graduate_ledgers table before importing}';

    protected $description = 'Import and clean the Graduate School Ledger CSV into the database';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        // Resolve relative paths from the project base path
        if (!str_starts_with($filePath, '/') && !str_contains($filePath, ':\\')) {
            $filePath = base_path($filePath);
        }

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            GraduateLedger::truncate();
            $this->warn('Existing records cleared.');
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error('Could not open CSV file.');
            return self::FAILURE;
        }

        // Skip the header row
        fgetcsv($handle);

        $imported  = 0;
        $skipped   = 0;
        $rowNumber = 1;

        $this->info('Importing records...');
        $bar = $this->output->createProgressBar();
        $bar->start();

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // CSV column positions (0-indexed):
            // 0  = student_name
            // 1  = course
            // 2  = school_year
            // 3  = semester_short
            // 4  = semester (full name)
            // 5  = units
            // 6  = transaction_date
            // 7  = reference_or_jev_number
            // 8  = particulars
            // 9  = tuition_per_unit_or_misc
            // 10 = ar_payment
            // 11 = amount
            // 12 = remarks
            // 13 = input_by

            $studentName = $this->clean($row[0] ?? '');

            // Skip rows with no student name
            if (empty($studentName)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $transactionDate = $this->parseDate($row[6] ?? '');
            $amount          = $this->parseAmount($row[11] ?? '0');
            $tuition         = $this->parseAmount($row[9] ?? '0');
            $arPayment       = $this->normalizeArPayment($row[10] ?? '');

            GraduateLedger::create([
                'student_name'            => $studentName,
                'course'                  => $this->clean($row[1] ?? ''),
                'school_year'             => $this->clean($row[2] ?? ''),
                'semester_short'          => $this->clean($row[3] ?? ''),
                'semester'                => $this->clean($row[4] ?? ''),
                'units'                   => is_numeric($row[5] ?? '') ? (int) $row[5] : null,
                'transaction_date'        => $transactionDate,
                'reference_or_jev_number' => $this->clean($row[7] ?? ''),
                'particulars'             => $this->clean($row[8] ?? ''),
                'tuition_per_unit_or_misc'=> $tuition,
                'ar_payment'              => $arPayment,
                'amount'                  => $amount,
                'remarks'                 => $this->clean($row[12] ?? ''),
                'input_by'                => $this->clean($row[13] ?? ''),
            ]);

            $imported++;
            $bar->advance();
        }

        fclose($handle);
        $bar->finish();
        $this->newLine();

        $this->info("✅ Done! Imported: {$imported} | Skipped (blank): {$skipped}");

        return self::SUCCESS;
    }

    /**
     * Trim whitespace and normalize dashes/em-dashes.
     */
    private function clean(?string $value): string
    {
        $value = str_replace(['−', '–', '—'], '-', (string) $value);
        return trim($value);
    }

    /**
     * Parse M/D/YYYY or YYYY-MM-DD dates into Y-m-d format.
     * Returns null for blank/invalid dates.
     */
    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if (empty($value)) {
            return null;
        }

        try {
            // Handle M/D/YYYY (e.g. 11/11/2016)
            if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $value)) {
                return Carbon::createFromFormat('m/d/Y', $value)->format('Y-m-d');
            }

            // Already in Y-m-d format
            if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $value)) {
                return $value;
            }

            // Fallback — let Carbon try
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Strip currency symbols and parentheses, return a float.
     * Parentheses e.g. (1,175.00) → negative.
     */
    private function parseAmount(?string $value): float
    {
        $value = trim((string) $value);

        if (empty($value)) {
            return 0.0;
        }

        $negative = str_contains($value, '(') && str_contains($value, ')');
        $numeric  = (float) preg_replace('/[^\d.]/', '', $value);

        return $negative ? -$numeric : $numeric;
    }

    /**
     * Normalize AR/Payment field to consistent casing.
     */
    private function normalizeArPayment(?string $value): string
    {
        $upper = strtoupper(trim((string) $value));

        return match (true) {
            $upper === 'AR'                               => 'AR',
            in_array($upper, ['PAYMENT', 'P', 'PAYMENR']) => 'Payment',
            in_array($upper, ['ADJUSTMENT', 'ADJ'])       => 'Adjustment',
            default                                       => $value ?? '',
        };
    }
}
