<?php

namespace App\Services;

use App\Models\FormInput;
use Illuminate\Support\Str;

class ReferenceNumberService
{
    /**
     * Generate a unique reference number
     * Format: OP-YYYYMMDD-XXXXX (where XXXXX is a random alphanumeric)
     */
    public function generate(): string
    {
        $date = now()->format('Ymd');
        $random = Str::upper(Str::random(5));
        $referenceNumber = 'OP-' . $date . '-' . $random;
        
        // Ensure uniqueness
        while (FormInput::where('reference_number', $referenceNumber)->exists()) {
            $random = Str::upper(Str::random(5));
            $referenceNumber = 'OP-' . $date . '-' . $random;
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