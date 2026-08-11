<?php

namespace App\Services;

// use App\Models\FormInput;
// use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\YearSequence;

class ReferenceNumberService
{
    /**
     * Generate a unique reference number
     * Format: OP-YYYYMMDD-XXXXX (where XXXXX is a random alphanumeric)
     */
    public function generate(): string
    {
        // $date = now()->format('Ymd');
        // $random = Str::upper(Str::random(5));
        // $referenceNumber = 'OP-'.$date.'-'.$random;

        // // Ensure uniqueness
        // while (FormInput::where('reference_number', $referenceNumber)->exists()) {
        //     $random = Str::upper(Str::random(5));
        //     $referenceNumber = 'OP-'.$date.'-'.$random;
        // }

        // return $referenceNumber;
        // 
        // $year = now()->year;
        // $month = now()->format('m');
    
        // // Count records created this year
        // $count = FormInput::whereYear('created_at', $year)->count() + 1;
    
        // // Pad the count to 5 digits
        // $sequence = str_pad($count, 5, '0', STR_PAD_LEFT);
    
        // return "{$year}-{$month}-{$sequence}";
        // 
        return DB::transaction(function () {
        
                    $now = now();
        
                    $year = $now->year;
                    $month = $now->month;
        
                    $sequence = YearSequence::lockForUpdate()
                        ->firstOrCreate(
                            ['year' => $year],
                            [
                                'month' => $month,
                                'current_number' => 0,
                            ]
                        );
        
                    $sequence->increment('current_number');
        
                    $sequence->update([
                        'month' => $month,
                    ]);
        
                    return sprintf(
                        '%d-%02d-%05d',
                        $year,
                        $month,
                        $sequence->current_number
                    );
                });
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
