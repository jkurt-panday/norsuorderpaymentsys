{{-- resources/views/pdf/op-landscape.blade.php --}}
<html>
<head>
<style>
    /* Locks the canvas this CSS assumes, independent of the controller's
       ->setPaper() call — if that ever changes without this file being
       updated, the layout still knows what size it's meant to render for. */
    @page {
        size: legal landscape;
        margin: 28mm;
    }
    body {
        margin: 0;
        font-family: 'DejaVu Sans', sans-serif;
        color: #000;
    }

    table.op-row {
        width: 100%;
        border-collapse: collapse;
    }

    table.op-row td.copy-cell {
        /* A6's real width (105mm), not a percentage of the legal-landscape
           page — this is what keeps each box visually identical in size to
           the standalone op-a6.blade.php output, regardless of how wide the
           legal page itself is. 3 x 105mm = 315mm, which fits comfortably
           inside legal landscape's 355.6mm width alongside the page margin. */
        width: 100mm;
        vertical-align: top;
        padding: 5mm 4.5mm 3mm 3mm;
        box-sizing: border-box;
        /* Keep each copy's box on one page — don't let it straddle a break. */
        page-break-inside: avoid;
        /* Cut guide between copies — sits in the gap created by the padding
           above, running the full height of the row so it's clear where to
           cut top-to-bottom to separate each copy. */
        border-right: 1px dashed #666;
    }

    table.op-row td.copy-cell:last-child {
        padding-right: 0;
        border-right: none;
    }

    .op-copy {
        border: 1px solid #000;
        padding: 2px 3px;
        font-size: 8.3px;
        line-height: 1.25;
        box-sizing: border-box;
        width: 100%;

        /* A6's real height (148mm) — matches the standalone A6 file so the
           box's proportions/margins look the same here as they do there. */
        min-height: 150mm;
    }

    table.op-table {
        width: 100%;
        border-collapse: collapse;
    }
</style>
</head>
<body>
    <table class="op-row">
        <tr>
            @foreach ($copyLabels as $copyLabel)
                <td class="copy-cell">
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
                                        {{ $formInput->staffInput->bankAccount->fund_cluster ?? '' }}
                                    </span>
                                </td>
                                <td style="vertical-align:top; padding:1px 0 0; white-space:nowrap;">
                                    <strong>Date:</strong>
                                    <span style="text-decoration:underline; padding:0 2px;">
                                        {{ $formInput->staffInput->ref_date ? \Carbon\Carbon::parse($formInput->staffInput->ref_date)->format('F j, Y') : '' }}
                                    </span>
                                </td>
                            </tr>
                        </table>

                        <h2 style="text-align:center; margin:3px 0; font-size:8.5px; letter-spacing:0.3px;">ORDER OF PAYMENT</h2>

                        <p style="margin:0;">The Collecting Officer</p>
                        <p style="margin:0 0 3px;">Cash / Treasury Unit</p>

                        <p style="text-align:center; margin:0 0 2px;">Please issue Official Receipt in favor of:</p>
                        <p style="text-align:center; margin:0 0 1px; font-weight:bold; word-wrap:break-word; overflow-wrap:break-word; text-transform: uppercase;">
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
                            $purposeRaw = trim(($formInput->membership->member_desc ?? '') . ' - ' . '₱' . number_format($formInput->amount, 2) . ' - ' . ($formInput->staffInput->purpose ?? ''));
                            $purposeText = preg_replace('/\s+/u', ' ', $purposeRaw);

                            $purposeFontSize = 7.0;
                            $minFontSize = 4.2;
                            $baseCharsPerLine = 100;

                            $wrapWidth = (int) round(($baseCharsPerLine * 6.3) / $purposeFontSize);
                            $purposeLines = wrapToLines($purposeText, $wrapWidth);

                            while (count($purposeLines) > 3 && $purposeFontSize > $minFontSize) {
                                $purposeFontSize = round($purposeFontSize - 0.2, 2);
                                $wrapWidth = (int) round(($baseCharsPerLine * 6.3) / $purposeFontSize);
                                $purposeLines = wrapToLines($purposeText, $wrapWidth);
                            }
                        @endphp
                        @foreach ($purposeLines as $line)
                            <p style="text-align:center; margin:0; padding:0 1px 1px; font-size:{{ $purposeFontSize }}px; border-bottom:1px solid #000; min-height:{{ $purposeFontSize + 0.3 }}px;">
                                {{ $line }}
                            </p>
                        @endforeach

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
                        <table class="op-table" border="1" style="margin-bottom:2px; table-layout:fixed;">
                            <colgroup>
                                <col style="width:25%;">
                                <col style="width:45%;">
                                <col style="width:30%;">
                            </colgroup>
                            <tr>
                                <th style="padding:1.5px 2px; font-size:5.2px;">No.</th>
                                <th style="padding:1.5px 2px; font-size:5.2px;">Name of Bank</th>
                                <th style="padding:1.5px 2px; font-size:5.2px;">Amount</th>
                            </tr>
                            <tr>
                                <td style="padding:1.5px 2px; text-align:center; word-wrap:break-word; overflow-wrap:break-word;">{{ $formInput->staffInput->bankAccount->account_num ?? 'N/A' }}</td>
                                <td style="padding:1.5px 2px; text-align:center; word-wrap:break-word; overflow-wrap:break-word;">{{ $formInput->staffInput->bankAccount->bank_name ?? 'N/A' }}</td>
                                <td style="padding:1.5px 2px; text-align:right;">₱{{ number_format($formInput->amount, 2) }}</td>
                            </tr>
                        </table>

                        {{-- Total --}}
                        <table class="op-table" style="margin-top: 15px; margin-bottom:3px;">
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

                        <p style="margin:40px 0 0;">OR No.: ______________________</p>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>