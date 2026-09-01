<?php

namespace Tests\Unit;

use App\Services\GraduateLedgerImportClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GraduateLedgerImportClassifierTest extends TestCase
{
    #[DataProvider('classificationCases')]
    public function test_it_classifies_import_rows(
        ?string $rawType,
        mixed $rawAmount,
        string $expectedType,
        ?string $expectedWarning,
    ): void {
        $result = (new GraduateLedgerImportClassifier)->classify($rawType, $rawAmount);

        self::assertSame($expectedType, $result['entry_type']);
        self::assertSame($expectedWarning, $result['warning']);
    }

    /** @return iterable<string, array{string|null, mixed, string, string|null}> */
    public static function classificationCases(): iterable
    {
        yield 'parentheses are a payment' => [null, '(1,250.00)', 'payment', GraduateLedgerImportClassifier::WARNING_NEGATIVE_BLANK_TYPE];
        yield 'negative number with blank type is a payment' => [null, -1250.00, 'payment', GraduateLedgerImportClassifier::WARNING_NEGATIVE_BLANK_TYPE];
        yield 'negative amount labeled AR is a payment' => ['AR', '-1,250.00', 'payment', GraduateLedgerImportClassifier::WARNING_NEGATIVE_LABELED_AR];
        yield 'positive amount labeled PAYMENT remains a payment' => ['PAYMENT', '1,250.00', 'payment', GraduateLedgerImportClassifier::WARNING_PAYMENT_MISSING_PARENTHESES];
        yield 'adjustment takes precedence over a negative amount' => ['ADJUSTMENT', '-50.00', 'adjustment', null];
        yield 'ordinary positive amount is AR' => [null, '1,250.00', 'ar', null];
    }
}
