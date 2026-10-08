<?php

namespace Tests\Unit;

use App\Services\BalanceCalculator;
use PHPUnit\Framework\TestCase;

class BalanceCalculatorTest extends TestCase
{
    public function test_addition_preserves_two_decimal_precision(): void
    {
        $result = BalanceCalculator::add('0.10', '0.20');
        $this->assertSame('0.30', $result);

        $result = BalanceCalculator::add('100.55', '200.45');
        $this->assertSame('301.00', $result);
    }

    public function test_subtraction_preserves_two_decimal_precision(): void
    {
        $result = BalanceCalculator::subtract('100.00', '33.33');
        $this->assertSame('66.67', $result);
    }

    public function test_summarize_handles_ar_payments_and_adjustments(): void
    {
        $records = [
            (object) ['entry_type' => 'ar', 'amount' => 5000.50],
            (object) ['entry_type' => 'payment', 'amount' => 2000.25],
            (object) ['entry_type' => 'adjustment', 'amount' => 500.25],
        ];

        $summary = BalanceCalculator::summarize($records);

        $this->assertSame(5000.50, $summary['totalCharges']);
        $this->assertSame(2000.25, $summary['totalPayments']);
        $this->assertSame(500.25, $summary['totalAdjustments']);
        $this->assertSame(2500.00, $summary['outstandingBalance']);
    }

    public function test_summarize_handles_floating_point_imprecision_without_drift(): void
    {
        // 0.10 repeated 10 times in standard float arithmetic produces 0.9999999999999999
        $records = [];
        for ($i = 0; $i < 10; $i++) {
            $records[] = (object) ['entry_type' => 'ar', 'amount' => 0.10];
        }

        $summary = BalanceCalculator::summarize($records);

        $this->assertSame(1.00, $summary['totalCharges']);
        $this->assertSame(1.00, $summary['outstandingBalance']);
    }

    public function test_summarize_handles_empty_records(): void
    {
        $summary = BalanceCalculator::summarize([]);

        $this->assertSame(0.00, $summary['totalCharges']);
        $this->assertSame(0.00, $summary['totalPayments']);
        $this->assertSame(0.00, $summary['totalAdjustments']);
        $this->assertSame(0.00, $summary['outstandingBalance']);
    }

    public function test_summarize_law_handles_legacy_labels(): void
    {
        $records = [
            (object) ['entry_type' => null, 'ar_or_payment' => 'ASSESSMENT', 'amount' => 10000.00],
            (object) ['entry_type' => null, 'ar_or_payment' => 'PAYMENT', 'amount' => 4500.00],
        ];

        $summary = BalanceCalculator::summarizeLaw($records);

        $this->assertSame(10000.00, $summary['totalCharges']);
        $this->assertSame(4500.00, $summary['totalPayments']);
        $this->assertSame(5500.00, $summary['outstandingBalance']);
    }
}
