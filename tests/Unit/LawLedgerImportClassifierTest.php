<?php

namespace Tests\Unit;

use App\Services\LawLedgerImportClassifier;
use Tests\TestCase;

class LawLedgerImportClassifierTest extends TestCase
{
    private LawLedgerImportClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new LawLedgerImportClassifier();
    }

    public function test_it_classifies_explicit_ar_with_positive_amount()
    {
        $result = $this->classifier->classify('AR', 1000);
        $this->assertEquals('ar', $result['entry_type']);

        $result = $this->classifier->classify('ASSESSMENT', 500.50);
        $this->assertEquals('ar', $result['entry_type']);
    }

    public function test_it_classifies_explicit_payment_with_negative_amount()
    {
        $result = $this->classifier->classify('PAYMENT', -1000);
        $this->assertEquals('payment', $result['entry_type']);

        $result = $this->classifier->classify('P', -500);
        $this->assertEquals('payment', $result['entry_type']);
    }

    public function test_it_classifies_explicit_adjustment()
    {
        $result = $this->classifier->classify('ADJ', 100);
        $this->assertEquals('adjustment', $result['entry_type']);

        $result = $this->classifier->classify('ADJUSTMENT', -100);
        $this->assertEquals('adjustment', $result['entry_type']);
    }

    public function test_it_handles_blank_type_with_negative_amount_as_payment()
    {
        $result = $this->classifier->classify('', -1000);
        $this->assertEquals('payment', $result['entry_type']);
    }

    public function test_it_handles_blank_type_with_positive_amount_as_ar()
    {
        $result = $this->classifier->classify('', 1000);
        $this->assertEquals('ar', $result['entry_type']);
    }

    public function test_it_parses_parentheses_notation_as_negative()
    {
        $result = $this->classifier->classify('', '(1000)');
        $this->assertEquals('payment', $result['entry_type']);

        $result = $this->classifier->classify('', '(1,500.50)');
        $this->assertEquals('payment', $result['entry_type']);
    }

    public function test_it_classifies_ar_even_when_negative()
    {
        // Explicitly labeled AR takes precedence even with negative amount
        $result = $this->classifier->classify('AR', -1000);
        $this->assertEquals('ar', $result['entry_type']);
    }

    public function test_it_handles_payment_with_positive_amount()
    {
        // Warning case - payment labeled but positive amount
        $result = $this->classifier->classify('PAYMENT', 1000);
        $this->assertEquals('payment', $result['entry_type']);
    }

    public function test_it_classifies_various_ar_keywords()
    {
        $keywords = ['AR', 'A/R', 'ASSESSMENT', 'CHARGE', 'BILLING', 'TUITION', 'FEE'];
        
        foreach ($keywords as $keyword) {
            $result = $this->classifier->classify($keyword, 1000);
            $this->assertEquals('ar', $result['entry_type'], "Failed for keyword: {$keyword}");
        }
    }

    public function test_it_classifies_various_payment_keywords()
    {
        $keywords = ['PAYMENT', 'PAY', 'P', 'PMT', 'RECEIPT', 'OR', 'CASH', 'CHECK', 'ONLINE'];
        
        foreach ($keywords as $keyword) {
            $result = $this->classifier->classify($keyword, -1000);
            $this->assertEquals('payment', $result['entry_type'], "Failed for keyword: {$keyword}");
        }
    }

    public function test_it_classifies_various_adjustment_keywords()
    {
        $keywords = ['ADJ', 'ADJUSTMENT', 'ADJUSTMENTS', 'CREDIT MEMO', 'DEBIT MEMO'];
        
        foreach ($keywords as $keyword) {
            $result = $this->classifier->classify($keyword, 100);
            $this->assertEquals('adjustment', $result['entry_type'], "Failed for keyword: {$keyword}");
        }
    }

    public function test_it_handles_string_amounts_with_currency_symbols()
    {
        $result = $this->classifier->classify('', '$1,000.00');
        $this->assertEquals('ar', $result['entry_type']);

        $result = $this->classifier->classify('', '₱1,500.50');
        $this->assertEquals('ar', $result['entry_type']);
    }

    public function test_it_handles_numeric_zero_as_ar()
    {
        $result = $this->classifier->classify('', 0);
        $this->assertEquals('ar', $result['entry_type']);
    }

    public function test_it_handles_na_as_blank_type()
    {
        $result = $this->classifier->classify('N/A', -1000);
        $this->assertEquals('payment', $result['entry_type']);

        $result = $this->classifier->classify('NA', 1000);
        $this->assertEquals('ar', $result['entry_type']);
    }

    public function test_it_is_case_insensitive()
    {
        $result = $this->classifier->classify('ar', 1000);
        $this->assertEquals('ar', $result['entry_type']);

        $result = $this->classifier->classify('payment', -1000);
        $this->assertEquals('payment', $result['entry_type']);

        $result = $this->classifier->classify('Payment', -500);
        $this->assertEquals('payment', $result['entry_type']);

        $result = $this->classifier->classify('ADJUSTMENT', 100);
        $this->assertEquals('adjustment', $result['entry_type']);
    }

    public function test_it_handles_whitespace_in_type()
    {
        $result = $this->classifier->classify('  AR  ', 1000);
        $this->assertEquals('ar', $result['entry_type']);

        $result = $this->classifier->classify(' PAYMENT ', -1000);
        $this->assertEquals('payment', $result['entry_type']);
    }

    public function test_it_handles_unknown_type_with_amount_sign()
    {
        $result = $this->classifier->classify('UNKNOWN', -1000);
        $this->assertEquals('payment', $result['entry_type']);

        $result = $this->classifier->classify('MISC', 1000);
        $this->assertEquals('ar', $result['entry_type']);
    }

    public function test_it_handles_negative_string_amounts()
    {
        $result = $this->classifier->classify('', '-1000');
        $this->assertEquals('payment', $result['entry_type']);

        $result = $this->classifier->classify('', '-1,500.50');
        $this->assertEquals('payment', $result['entry_type']);
    }

    public function test_it_handles_edge_case_parentheses_without_decimals()
    {
        $result = $this->classifier->classify('', '(1000)');
        $this->assertEquals('payment', $result['entry_type']);
    }

    public function test_it_handles_non_numeric_strings_as_zero()
    {
        $result = $this->classifier->classify('', 'invalid');
        $this->assertEquals('ar', $result['entry_type']);
    }

    public function test_it_handles_float_amounts()
    {
        $result = $this->classifier->classify('AR', 1234.56);
        $this->assertEquals('ar', $result['entry_type']);

        $result = $this->classifier->classify('PAYMENT', -1234.56);
        $this->assertEquals('payment', $result['entry_type']);
    }
}
