<?php

if (!function_exists('wrapToLines')) {
    function wrapToLines(string $text, int $charsPerLine = 50): array
    {
        if (trim($text) === '') {
            return [''];
        }

        $wrapped = wordwrap($text, $charsPerLine, "\n", true);
        return explode("\n", $wrapped);
    }
}

if (! function_exists('numberToWords')) {
    /**
     * Convert a peso amount (e.g. 3372.01) into words, e.g.
     * "Three Thousand Three Hundred Seventy Two Pesos and One Cent"
     *
     * Used by the Order of Payment PDF (resources/views/pdf/...).
     */
    function numberToWords(float|string $amount): string
    {
        $value = (float) $amount;
        $pesos = (int) floor($value);
        $cents = (int) round(($value - $pesos) * 100);

        $pesosWords = integerToWords($pesos).' Peso'.($pesos === 1 ? '' : 's');

        if ($cents === 0) {
            return $pesosWords.' Only';
        }

        $centsWords = integerToWords($cents).' Cent'.($cents === 1 ? '' : 's');

        return $pesosWords.' and '.$centsWords;
    }
}

if (! function_exists('integerToWords')) {
    /**
     * Convert a whole, non-negative integer into English words.
     * Supports up to billions — far more than any peso amount needs.
     */
    function integerToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = [
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen',
        ];
        $tens = [
            '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
        ];

        $threeDigitsToWords = function (int $n) use ($ones, $tens): string {
            $str = '';
            if ($n >= 100) {
                $str .= $ones[intdiv($n, 100)].' Hundred ';
                $n %= 100;
            }
            if ($n >= 20) {
                $str .= $tens[intdiv($n, 10)].' ';
                $n %= 10;
            }
            if ($n > 0) {
                $str .= $ones[$n].' ';
            }

            return trim($str);
        };

        $groups = ['', ' Thousand', ' Million', ' Billion'];
        $groupIndex = 0;
        $words = '';

        while ($number > 0) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                $words = $threeDigitsToWords($chunk).$groups[$groupIndex].' '.$words;
            }
            $number = intdiv($number, 1000);
            $groupIndex++;
        }

        return trim($words);
    }
}