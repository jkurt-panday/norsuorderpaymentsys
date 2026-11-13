<?php

namespace App\Services;

/**
 * Classifier for Graduate Ledger import rows.
 * Mirrors the Law ledger classifier: returns only entry_type, no warnings.
 * Broader aliases for payment and adjustment match real-world Excel data.
 */
final class GraduateLedgerImportClassifier
{
    /**
     * @return array{entry_type: 'ar'|'payment'|'adjustment'}
     */
    public function classify(?string $rawType, mixed $rawAmount): array
    {
        $type = strtoupper(trim($rawType ?? ''));
        $amount = $this->parseAmount($rawAmount);

        if ($this->isAdjustment($type)) {
            return ['entry_type' => 'adjustment'];
        }

        if ($this->isAR($type)) {
            return ['entry_type' => 'ar'];
        }

        if ($this->isPayment($type)) {
            return ['entry_type' => 'payment'];
        }

        // Blank / unknown — infer from sign
        if ($type === '' || $type === 'N/A' || $type === 'NA') {
            return ['entry_type' => $amount < 0 ? 'payment' : 'ar'];
        }

        return ['entry_type' => $amount < 0 ? 'payment' : 'ar'];
    }

    private function isAdjustment(string $type): bool
    {
        return in_array($type, [
            'ADJ', 'ADJUSTMENT', 'ADJUSTMENTS',
            'CREDIT MEMO', 'DEBIT MEMO', 'MEMO',
        ], true);
    }

    private function isAR(string $type): bool
    {
        return in_array($type, [
            'AR', 'A/R', 'ASSESSMENT', 'ASSESSMENTS',
            'CHARGE', 'CHARGES', 'BILLING', 'BILL',
            'TUITION', 'FEE', 'FEES',
        ], true);
    }

    private function isPayment(string $type): bool
    {
        return in_array($type, [
            'PAYMENT', 'PAYMENTS', 'PAY', 'P', 'PMT', 'PAYMENR',
            'SETTLED', 'RECEIPT', 'RECEIPTS', 'OR',
            'CASH', 'CHECK', 'CHEQUE', 'ONLINE', 'BANK',
        ], true);
    }

    private function parseAmount(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return 0.0;
        }

        $cleaned = trim(str_replace(['−', '–', '—'], '-', $value));

        // Parentheses = negative (accounting format)
        if (preg_match('/^\(([0-9,]+\.?[0-9]*)\)$/', $cleaned, $m)) {
            return -1 * (float) str_replace(',', '', $m[1]);
        }

        $cleaned = preg_replace('/[^\d.\-]/', '', $cleaned);

        return (float) $cleaned;
    }
}
