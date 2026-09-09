<?php

namespace App\Services;

final class GraduateLedgerImportClassifier
{
    public const WARNING_NEGATIVE_BLANK_TYPE = 'negative_blank_type';

    public const WARNING_NEGATIVE_LABELED_AR = 'negative_labeled_ar';

    public const WARNING_PAYMENT_MISSING_PARENTHESES = 'payment_missing_parentheses';

    /**
     * @return array{
     *     entry_type: 'ar'|'payment'|'adjustment',
     *     warning: 'negative_blank_type'|'negative_labeled_ar'|'payment_missing_parentheses'|null
     * }
     */
    public function classify(?string $rawType, mixed $rawAmount): array
    {
        $type = strtoupper(trim($rawType ?? ''));
        $amount = $this->normalizeAmountText($rawAmount);
        $hasParentheses = str_contains($amount, '(') && str_contains($amount, ')');
        $isNegative = $this->isNegative($rawAmount);

        if ($type === 'ADJUSTMENT' || $type === 'ADJ' || str_contains($type, 'ADJUST')) {
            return ['entry_type' => 'adjustment', 'warning' => null];
        }

        if ($hasParentheses || $isNegative) {
            $warning = null;

            if ($type === 'AR') {
                $warning = self::WARNING_NEGATIVE_LABELED_AR;
            } elseif ($type === '' || ! $this->isPaymentType($type)) {
                $warning = self::WARNING_NEGATIVE_BLANK_TYPE;
            }

            return ['entry_type' => 'payment', 'warning' => $warning];
        }

        if ($this->isPaymentType($type)) {
            return [
                'entry_type' => 'payment',
                'warning' => self::WARNING_PAYMENT_MISSING_PARENTHESES,
            ];
        }

        return ['entry_type' => 'ar', 'warning' => null];
    }

    private function isPaymentType(string $type): bool
    {
        return in_array($type, ['PAYMENT', 'P', 'PAYMENR', 'SETTLED'], true);
    }

    private function isNegative(mixed $rawAmount): bool
    {
        if (is_int($rawAmount) || is_float($rawAmount)) {
            return $rawAmount < 0;
        }

        $amount = $this->normalizeAmountText($rawAmount);

        return preg_match('/^[^\d]*-\s*\d/', $amount) === 1;
    }

    private function normalizeAmountText(mixed $rawAmount): string
    {
        return trim(str_replace(['−', '–', '—'], '-', (string) ($rawAmount ?? '')));
    }
}
