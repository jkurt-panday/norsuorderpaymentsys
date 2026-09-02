{{-- resources/views/pdf/op-a6.blade.php --}}
<html>
<head>
<style>
    /* A6 portrait: 105mm x 148mm. Locking it here (independent of the
       controller's ->setPaper() call) so this file always knows what
       canvas it's designing for, even if that call ever changes. */
    @page {
        size: a6 portrait;
        margin: 4mm;
    }
    body {
        margin: 0;
        font-family: 'DejaVu Sans', sans-serif;
        color: #000;
    }

    .op-copy {
        border: 1px solid #000;
        padding: 0px 1px ;
        font-size: 6.7px;
        line-height: 1.55;
        width: 100%;

        /* One copy = one page. Never let a copy's box straddle two pages —
           if it doesn't fit, it should shrink (see purpose auto-fit below),
           not spill over. */
        page-break-inside: avoid;
    }

    .op-copy:not(:last-child) {
        page-break-after: always;
    }

    table.op-table {
        width: 100%;
        border-collapse: collapse;
    }
</style>
</head>
<body>
    @foreach ($copyLabels as $copyLabel)
        <div class="op-copy">

            <p style="text-align:right; font-size:5.6px; margin:0 0 2px;">{{ $copyLabel }}</p>

            {{-- Entity / Serial / Fund Cluster / Date --}}
            <table class="op-table" style="margin-bottom:2px;">
                <tr>
                    <td style="vertical-align:top; padding:0 6px 1px 0;">
                        <strong style="white-space:nowrap;">Entity Name:</strong>
                        <span style="text-decoration:underline; font-weight:bold;">
                            NEGROS ORIENTAL STATE UNIVERSITY
                        </span>
                    </td>
                    <td style="vertical-align:top; padding:0 0 1px; white-space:nowrap; text-align:left;">
                        <strong>Serial No.</strong> {{ $formInput->reference_number }}
                    </td>
                </tr>
                    <tr>
                        <td style="vertical-align:top; padding:1px 6px 0 0;">
                            <strong style="white-space:nowrap;">Fund Cluster:</strong>
                            <span style="border:1px solid #999; padding:0 5px 0 2px; display:inline-block;">
                                {{ $formInput->staffInput?->bankAccount?->fund_cluster ?? '' }}
                            </span>
                        </td>
                        <td style="vertical-align:top; padding:1px 0 0; white-space:nowrap;">
                            <strong>Date:</strong>
                            <span style="text-decoration:underline; padding:0 2px;">
                                {{ $formInput->created_at ? \Carbon\Carbon::parse($formInput->created_at)->format('F j, Y') : '' }}
                            </span>
                        </td>
                    </tr>
            </table>

            <h2 style="text-align:center; margin:3px 0; font-size:8.5px; letter-spacing:0.3px;">ORDER OF PAYMENT</h2>

            <p style="margin:0;">The Collecting Officer</p>
            <p style="margin:0 0 3px;">Cash / Treasury Unit</p>

            <p style="text-align:center; margin:0 0 0;">Please issue Official Receipt in favor of:</p>
            <p style="text-align:center; margin:0 0 0; font-weight:bold; word-wrap:break-word; overflow-wrap:break-word; text-transform: uppercase;">
                 {{ $formInput->firstname_or_office }} {{ $formInput->middlename_or_project }} {{ $formInput->lastname_or_agency }} 
            </p>
            <p style="text-align:center; margin:0 0 1px; text-decoration:underline; word-wrap:break-word; overflow-wrap:break-word;">{{ $formInput->address }}</p>
            <p style="text-align:center; margin:0 0 20px; font-size:5.2px;">(Name and Address/ Office of Payor)</p>

            {{-- Amount --}}
            <table class="op-table" style="margin-bottom:2px;">
                <tr>
                    <td style="vertical-align:bottom; padding:1px 4px 2px 0; white-space:nowrap;">in the amount of</td>
                    <td style="vertical-align:bottom; text-align:center; border-bottom:1px solid #000; word-wrap:break-word; overflow-wrap:break-word;">
                        {{ strtoupper(numberToWords($formInput->amount)) }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:1px 0 2px;"></td>
                    <td style="padding:1px 0 0; text-align:center; border-bottom:1px solid #000;">
                        ₱{{ number_format($formInput->amount, 2) }}
                    </td>
                </tr>
            </table>

<p style="margin:2px 0 1px;">for payment of</p>
<p style="margin:0 0 2px; font-size:5.2px;">(Purpose)</p>

@php
    $purposeText = $formInput->staffInput?->purpose ?? '';

    $purposeFontSize = 6.5;
    $minFontSize = 5.2;
    $maxLines = 4;
    $baseCharsPerLine = 100;

    // Wraps the FULL text (respecting real \n breaks) into actual line
    // strings — not just a count this time, since we need the real lines
    // to render each one in its own bordered <p>.
    $wrapAll = function (string $text, float $fontSize) use ($baseCharsPerLine): array {
        $wrapWidth = (int) round(($baseCharsPerLine * 6.3) / $fontSize);
        $lines = [];

        foreach (explode("\n", $text) as $segment) {
            $wrapped = wrapToLines($segment, $wrapWidth);
            $lines = array_merge($lines, $wrapped ?: ['']);
        }

        return $lines;
    };

    $purposeLines = $wrapAll($purposeText, $purposeFontSize);

    while (count($purposeLines) > $maxLines && $purposeFontSize > $minFontSize) {
        $purposeFontSize = round($purposeFontSize - 0.2, 2);
        $purposeLines = $wrapAll($purposeText, $purposeFontSize);
    }

    $lineHeightPx = round($purposeFontSize * 1.3, 1);

    // Reserved height stays fixed at $maxLines' worth for normal input —
    // only grows past that in the rare case content still needs more
    // lines even at the smallest font (so nothing is ever hidden).
    $slotCount = max($maxLines, count($purposeLines));
    $purposeBoxHeight = round($lineHeightPx * $slotCount, 1);
@endphp

{{-- flex + justify-content:flex-end anchors the lines to the BOTTOM of this
    reserved space — so short purpose text sits right above "Per Reference
    Doc." instead of floating near the top with empty space below it. --}}
<div style="display:flex; flex-direction:column; justify-content:flex-end; height:{{ $purposeBoxHeight }}px; box-sizing:border-box;">
    @foreach ($purposeLines as $line)
        <p style="text-align:center; margin:0; padding:0 0; font-size:{{ $purposeFontSize }}px; line-height:{{ $lineHeightPx }}px; width:100%; box-sizing:border-box; word-wrap:break-word; overflow-wrap:break-word;">
            {{ $line }}
        </p>
    @endforeach
</div>
            {{-- Per Reference Doc / UACS --}}
            <table class="op-table" style="margin-bottom:10px; margin-top:15px;">
                <tr>
                    <td style="vertical-align:top; padding:1px 4px 1px 0; white-space:nowrap;">Per Reference Doc.</td>
                    <td style="vertical-align:top; padding:1px 0; word-wrap:break-word; overflow-wrap:break-word;">
                        {{ $formInput->staffInput->referenceDocument->original_filename ?? '' }}
                        &nbsp;dated
                        <span style="text-decoration:underline; padding:0 2px;">
                            {{ $formInput->staffInput->ref_date ? \Carbon\Carbon::parse($formInput->staffInput->ref_date)->format('j-M-Y') : '' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align:top; padding:1px 4px 1px 0; white-space:nowrap;">UACS</td>
                    <td style="vertical-align:top; padding:1px 0; word-wrap:break-word; overflow-wrap:break-word;">
                        {{ $formInput->staffInput->uacs->object_code ?? '' }} - {{ $formInput->staffInput->uacs->account_title ?? '' }}
                    </td>
                </tr>
            </table>

            {{-- Bank Account table --}}
            <p style="margin:2px 0 1px;">Please deposit the collections under Bank Account/s:</p>
            <table class="op-table" border="1" style="margin-bottom:2px; margin-top: 5px; table-layout:fixed;">
                <colgroup>
                    <col style="width:25%;">
                    <col style="width:45%;">
                    <col style="width:30%;">
                </colgroup>
                <tr>
                    <th style="padding:1.5px 2px; font-size:6.2px;">No.</th>
                    <th style="padding:1.5px 2px; font-size:6.2px;">Name of Bank</th>
                    <th style="padding:1.5px 2px; font-size:6.2px;">Amount</th>
                </tr>
                <tr>
                    <td style="padding:1.5px 2px; text-align:center; word-wrap:break-word; overflow-wrap:break-word;">{{ $formInput->staffInput->bankAccount->account_num ?? 'N/A' }}</td>
                    <td style="padding:1.5px 2px; text-align:center; word-wrap:break-word; overflow-wrap:break-word;">{{ $formInput->staffInput->bankAccount->bank_name ?? 'N/A' }}</td>
                    <td style="padding:1.5px 2px; text-align:right;">₱{{ number_format($formInput->amount, 2) }}</td>
                </tr>
            </table>

            {{-- Total --}}
            <table class="op-table" style="margin-top: 15px; margin-bottom:1px;">
                <tr>
                    <td style="text-align:right; padding:1px 3px 2px 0;"><strong>TOTAL</strong></td>
                    <td style="width:35%; text-align:right; padding:1px 3px 2px; border-bottom:1px solid #000;">
                        <strong>₱{{ number_format($formInput->amount, 2) }}</strong>
                    </td>
                </tr>
            </table>

                        {{-- Signature block --}}
                        <table class="op-table" style="margin-bottom:2px;">
                            <tr>
                                <td style="text-align:right;">
                                    <p style="margin:8px 0 1px;">(SGD)</p>
                                    <p style="margin:13px 0 1px; text-decoration:underline; font-weight:bold;">
                                        MAURICE ANAVER B. DORDADO, CPA
                                    </p>
                                    <p style="margin:0;">Head of Accounting/Division/Unit</p>
                                    <p style="margin:0;">Authorized Official</p>
                                </td>
                            </tr>
                        </table>

            @php
    $orNo = $formInput->staffInput?->or_no ?? '';
    $orDate = $formInput->staffInput?->or_date ? \Carbon\Carbon::parse($formInput->staffInput?->or_date)->format('F j, Y') : '';
@endphp

@if($orDate)
    <p style="margin:3px 0px 0px 0px;"><strong>{{ $orDate }}</strong></p>
@endif
<p style="margin:2px 0px 6px 0px;"><strong>OR No.</strong>: <span style="text-decoration:underline;">{{ $orNo ?: '______________________' }}</span></p>
        </div>
    @endforeach
</body>
</html>