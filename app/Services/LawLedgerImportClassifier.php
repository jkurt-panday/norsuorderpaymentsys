<?php

namespace App\Services;

/**
 * Intelligent classifier for Law School Ledger import rows.
 * Handles ambiguous Excel column values to determine whether a row
 * represents an AR (assessment), payment, or adjustment.
 */
class LawLedgerImportClassifier
{
    /**
     * Classify an import row based on AR/Payment column value and amount.
     *
     * @param  string  $arPaymentCol  The value from AR_OR_PAYMENT column
     * @param  mixed  $amountCol  The amount value (may be negative, in parentheses, etc.)
     * @return array{entry_type: 'ar'|'payment'|'adjustment'}
     */
    public function classify(string $arPaymentCol, mixed $amountCol): array
    {
        $typeUpper = strtoupper(trim($arPaymentCol));
        $amount = $this->parseAmount($amountCol);

        // Explicit adjustment indicators
        if ($this->isAdjustment($typeUpper)) {
            return ['entry_type' => 'adjustment'];
        }

        // Explicit AR indicators
        if ($this->isAR($typeUpper)) {
            // If explicitly labeled AR but negative, likely data entry error - treat as AR anyway
            return ['entry_type' => 'ar'];
        }

        // Explicit payment indicators
        if ($this->isPayment($typeUpper)) {
            return ['entry_type' => 'payment'];
        }

        // Blank or unknown type - infer from amount
        if ($typeUpper === '' || $typeUpper === 'N/A' || $typeUpper === 'NA') {
            // Negative amounts without explicit type are typically payments
            if ($amount < 0) {
                return ['entry_type' => 'payment'];
            }
            // Positive amounts default to AR
            return ['entry_type' => 'ar'];
        }

        // Default fallback - positive = AR, negative = payment
        return $amount < 0 ? ['entry_type' => 'payment'] : ['entry_type' => 'ar'];
    }

    /**
     * Parse amount from various Excel formats.
     * Handles: negative numbers, parentheses notation (accounting format), strings.
     */
    private function parseAmount(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return 0.0;
        }

        $cleaned = trim($value);

        // Check for parentheses notation: "(1000)" or "(1,000.00)" = negative
        if (preg_match('/^\(([0-9,]+\.?[0-9]*)\)$/', $cleaned, $matches)) {
            $number = str_replace(',', '', $matches[1]);
            return -1 * (float) $number;
        }

        // Remove currency symbols and commas, keep negative sign and decimals
        $cleaned = preg_replace('/[^\d.\-]/', '', $cleaned);

        return (float) $cleaned;
    }

    /**
     * Check if the type string indicates an adjustment.
     */
    private function isAdjustment(string $typeUpper): bool
    {
        return in_array($typeUpper, [
            'ADJ',
            'ADJUSTMENT',
            'ADJUSTMENTS',
            'CREDIT MEMO',
            'DEBIT MEMO',
            'MEMO',
        ], true);
    }

    /**
     * Check if the type string indicates an AR (assessment/charge).
     */
    private function isAR(string $typeUpper): bool
    {
        return in_array($typeUpper, [
            'AR',
            'A/R',
            'ASSESSMENT',
            'ASSESSMENTS',
            'CHARGE',
            'CHARGES',
            'BILLING',
            'BILL',
            'TUITION',
            'FEE',
            'FEES',
        ], true);
    }

    /**
     * Check if the type string indicates a payment.
     */
    private function isPayment(string $typeUpper): bool
    {
        return in_array($typeUpper, [
            'PAYMENT',
            'PAYMENTS',
            'PAY',
            'P',
            'PMT',
            'RECEIPT',
            'RECEIPTS',
            'OR',
            'CASH',
            'CHECK',
            'CHEQUE',
            'ONLINE',
            'BANK',
        ], true);
    }
}
