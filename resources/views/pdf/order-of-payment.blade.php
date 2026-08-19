{{-- resources/views/pdf/order-of-payment.blade.php --}}
@php
    if (!function_exists('numberToWords')) {
        function numberToWords($number)
        {
            $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
                'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

            $number = (int) $number;
            if ($number == 0) {
                return 'Zero';
            }

            $convertChunk = function ($num) use (&$convertChunk, $ones, $tens) {
                if ($num == 0) return '';
                if ($num < 20) return $ones[$num] . ' ';
                if ($num < 100) return $tens[intdiv($num, 10)] . ' ' . $convertChunk($num % 10);
                return $ones[intdiv($num, 100)] . ' Hundred ' . $convertChunk($num % 100);
            };

            $words = '';
            if ($number >= 1000000) {
                $words .= $convertChunk(intdiv($number, 1000000)) . 'Million ';
                $number %= 1000000;
            }
            if ($number >= 1000) {
                $words .= $convertChunk(intdiv($number, 1000)) . 'Thousand ';
                $number %= 1000;
            }
            $words .= $convertChunk($number);

            return trim($words) . ' Pesos';
        }
    }
@endphp

{{--
    $copies is expected to have exactly 3 labels (e.g. Accounting Units Copy,
    Payor's Copy, Cash Units Copy) — laid out as two side-by-side on top and
    one full-width underneath, all on a single page.
--}}
<table width="100%" style="border-collapse:collapse;">
    <tr>
        <td style="width:50%; vertical-align:top; padding:6px;">
            @include('pdf.op-copy', ['copyLabel' => $copies[0] ?? ''])
        </td>
        <td style="width:50%; vertical-align:top; padding:6px;">
            @include('pdf.op-copy', ['copyLabel' => $copies[1] ?? ''])
        </td>
    </tr>
    <tr>
        <td style="width:50%; vertical-align:top; padding:6px;">
            @include('pdf.op-copy', ['copyLabel' => $copies[2] ?? ''])
        </td>
        <td style="width:50%;"></td>
    </tr>
</table>