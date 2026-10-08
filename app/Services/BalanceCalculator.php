<?php

namespace App\Services;

class BalanceCalculator
{
    private const SCALE = 2;

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function add(string $a, string $b): string
    {
        /** @var numeric-string */
        return bcadd($a, $b, self::SCALE);
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function subtract(string $a, string $b): string
    {
        /** @var numeric-string */
        return bcsub($a, $b, self::SCALE);
    }

    /**
     * @param  iterable<mixed>  $records
     * @return array{totalCharges: float, totalPayments: float, totalAdjustments: float, outstandingBalance: float}
     */
    public static function summarize(iterable $records): array
    {
        $totalCharges = '0.00';
        $totalPayments = '0.00';
        $totalAdjustments = '0.00';

        foreach ($records as $record) {
            $amount = number_format(abs((float) ($record->amount ?? 0)), 2, '.', '');
            $entryType = strtolower(trim((string) ($record->entry_type ?? '')));

            if ($entryType === 'ar') {
                $totalCharges = self::add($totalCharges, $amount);
            } elseif ($entryType === 'adjustment') {
                $totalAdjustments = self::add($totalAdjustments, $amount);
            } else {
                $totalPayments = self::add($totalPayments, $amount);
            }
        }

        $outstandingBalance = self::subtract(
            self::subtract($totalCharges, $totalPayments),
            $totalAdjustments
        );

        return [
            'totalCharges' => (float) $totalCharges,
            'totalPayments' => (float) $totalPayments,
            'totalAdjustments' => (float) $totalAdjustments,
            'outstandingBalance' => (float) $outstandingBalance,
        ];
    }

    /**
     * Law-ledger variant: no separate adjustments column.
     *
     * @param  iterable<mixed>  $records
     * @return array{totalCharges: float, totalPayments: float, outstandingBalance: float}
     */
    public static function summarizeLaw(iterable $records): array
    {
        $totalCharges = '0.00';
        $totalPayments = '0.00';

        foreach ($records as $record) {
            $amount = number_format(abs((float) ($record->amount ?? 0)), 2, '.', '');
            $entryType = strtolower(trim((string) ($record->entry_type ?? '')));

            if ($entryType === 'ar') {
                $totalCharges = self::add($totalCharges, $amount);
            } elseif (in_array($entryType, ['payment', 'adjustment'], true)) {
                $totalPayments = self::add($totalPayments, $amount);
            } else {
                $label = strtoupper(trim((string) ($record->ar_or_payment ?? '')));
                if ($label === 'AR' || $label === 'ASSESSMENT') {
                    $totalCharges = self::add($totalCharges, $amount);
                } else {
                    $totalPayments = self::add($totalPayments, $amount);
                }
            }
        }

        return [
            'totalCharges' => (float) $totalCharges,
            'totalPayments' => (float) $totalPayments,
            'outstandingBalance' => (float) self::subtract($totalCharges, $totalPayments),
        ];
    }
}
