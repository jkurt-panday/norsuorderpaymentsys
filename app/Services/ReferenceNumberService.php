<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\YearSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferenceNumberService
{
    public function generate(): string
    {
        return DB::transaction(function () {

            $now = now();

            $year = $now->year;
            $month = $now->month;

            $sequence = YearSequence::lockForUpdate()
                ->firstOrCreate(
                    ['year' => $year],
                    [
                        'month' => $month,
                        'op_number' => 0,
                    ]
                );

            $sequence->increment('op_number');

            $sequence->update([
                'month' => $month,
            ]);

            return sprintf(
                '%d-%02d-%05d',
                $year,
                $month,
                $sequence->op_number
            );
        });
    }

    /**
     * Generate a unique reference number
     * Format: OP-YYYYMMDD-XXXXX (where XXXXX is a random alphanumeric)
     */
    public function old_assess_ref_gen(): string
    {
        $date = now()->format('Ymd');
        $random = Str::upper(Str::random(5));
        $referenceNumber = 'OP-'.$date.'-'.$random;

        // Ensure uniqueness
        while (AssessmentForm::query()->where('reference_number', $referenceNumber)->exists()) {
            $random = Str::upper(Str::random(5));
            $referenceNumber = 'OP-'.$date.'-'.$random;
        }

        return $referenceNumber;
    }

    /**
     * 2026-08-AF-060, this is the format
     */
    public function assess_ref_gen(): string
    {
        // Generate reference number in a separate transaction to ensure sequence increments even if main transaction fails
        $referenceNumber = DB::transaction(function () {
            $now = now();

            $year = $now->year;
            $month = $now->month;

            $sequence = YearSequence::lockForUpdate()
                ->firstOrCreate(
                    ['year' => $year],
                    [
                        'month' => $month,
                        'current_number' => 0,
                        'assessment_number' => 0,
                    ]
                );

            $sequence->increment('assessment_number');

            $sequence->update([
                'month' => $month,
            ]);

            return sprintf(
                '%d-%02d-AF-%03d',
                $year,
                $month,
                $sequence->assessment_number
            );
        });

        // Ensure uniqueness by checking if reference already exists (shouldn't happen with proper locking, but safety check)
        while (AssessmentForm::query()->where('reference_number', $referenceNumber)->exists()) {
            // If somehow duplicate, generate a new one using the old method
            $date = now()->format('Ymd');
            $random = Str::upper(Str::random(5));
            $referenceNumber = 'AF-' . $date . '-' . $random;
        }

        return $referenceNumber;
    }

    /**
     * Validate reference number format
     */
    public function isValidFormat(string $referenceNumber): bool
    {
        return (bool) preg_match('/^OP-\d{8}-[A-Z0-9]{5}$/', $referenceNumber);
    }

    /**
     * Extract date from reference number
     */
    public function extractDate(string $referenceNumber): ?string
    {
        if ($this->isValidFormat($referenceNumber)) {
            $parts = explode('-', $referenceNumber);

            return $parts[1] ?? null;
        }

        return null;
    }
}
