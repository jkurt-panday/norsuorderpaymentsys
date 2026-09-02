<div style="position:relative; border:1px solid #000; padding:8px; font-family: 'DejaVu Sans', sans-serif; font-size:8.5px; line-height:1.5; color:#000; min-height:130mm; box-sizing:border-box; width:100%;">

    <p style="text-align:right; font-size:7.5px; margin:0 0 6px;">{{ $copyLabel }}</p>

    {{-- Entity/Serial/Fund Cluster/Date — auto layout + nowrap labels --}}
    <table width="100%" style="border-collapse:collapse; margin-bottom:6px;">
        <tr>
            <td style="vertical-align:top; padding:0 12px 3px 0;">
                <strong style="white-space:nowrap;">Entity Name:</strong>
                <span style="text-decoration:underline; font-weight:bold;">
                    NEGROS ORIENTAL STATE UNIVERSITY
                </span>
            </td>
            <td style="vertical-align:top; padding:0 0 3px; white-space:nowrap; text-align:left;">
                <strong>Serial No.</strong> {{ $formInput->reference_number }}
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top; padding:3px 12px 0 0;">
                <strong style="white-space:nowrap;">Fund Cluster:</strong>
                <span style="border:1px solid #999; padding: 1px 10px 2px 4px; display:inline-block;">
                    {{ $formInput->staffInput->bankAccount->fund_cluster ?? '' }}
                </span>
            </td>
            <td style="vertical-align:top; padding:3px 0 0; white-space:nowrap;">
                <strong>Date:</strong>
                <span style="text-decoration:underline; padding:0 4px;">
                    {{ $formInput->created_at ? \Carbon\Carbon::parse($formInput->created_at)->format('F j, Y') : '' }}
                </span>
            </td>
        </tr>
    </table>

    <h2 style="text-align:center; margin:8px 0; font-size:12px; letter-spacing:0.5px;">ORDER OF PAYMENT</h2>

    <p style="margin:0 0 2px;">The Collecting Officer</p>
    <p style="margin:0 0 10px;">Cash / Treasury Unit</p>

    <p style="text-align:center; margin:0 0 6px;">Please issue Official Receipt in favor of:</p>
    <p style="text-align:center; margin:0 0 3px; font-weight:bold; word-wrap:break-word; overflow-wrap:break-word;">
        , {{ $formInput->firstname_or_office }} {{ $formInput->middlename_or_project }} {{ $formInput->lastname_or_agency }} ,
    </p>
    <p style="text-align:center; margin:0 0 3px; text-decoration:underline; padding:0 0 2px; word-wrap:break-word; overflow-wrap:break-word;">{{ $formInput->address }}</p>
    <p style="text-align:center; margin:0 0 10px; font-size:7px;">(Name and Address/ Office of Payor)</p>

    {{-- "in the amount of" — label is short/fixed, value is a long underlined
         blank that should keep consuming whatever's left, so nowrap + auto is
         the right call here too. --}}
    <table width="100%" style="border-collapse:collapse; margin-bottom:6px;">
        <tr>
            <td style="vertical-align:bottom; padding:3px 8px 6px 0; white-space:nowrap;">in the amount of</td>
            <td style="vertical-align:bottom; text-align:center; border-bottom:1px solid #000; word-wrap:break-word; overflow-wrap:break-word;">
                {{ strtoupper(numberToWords($formInput->amount)) }}
            </td>
        </tr>
        <tr>
            <td style="padding:2px 0px 6px;"></td>
            <td style="padding:2px 0px 0px; text-align:center; border-bottom:1px solid #000;">
                ₱{{ number_format($formInput->amount, 2) }}
            </td>
        </tr>
    </table>

        <p style="margin:8px 0 2px;">for payment of</p>
        <p style="margin:0 0 6px; font-size:7px;">(Purpose)</p>


        @php
            $purposeBase = trim(($formInput->membership->member_desc ?? '') . ' - ' . '₱' . number_format($formInput->amount, 2) . ' - ' . ($formInput->staffInput->purpose ?? ''));

            // Wrap width scales inversely with font size (smaller font = more chars
            // fit per physical line). Base: 115 chars at 7.5px, extrapolated from there.
            $purposeTiers = [
                ['size' => 7.5, 'wrap' => 110],
                ['size' => 6.5, 'wrap' => 133],
                ['size' => 5.5, 'wrap' => 157],
                ['size' => 4.5, 'wrap' => 192],
            ];

            $purposeFontSize = end($purposeTiers)['size'];
            $purposeLines = wrapToLines($purposeBase, end($purposeTiers)['wrap']);

            foreach ($purposeTiers as $tier) {
                $lines = wrapToLines($purposeBase, $tier['wrap']);
                if (count($lines) <= 4) {
                    $purposeFontSize = $tier['size'];
                    $purposeLines = $lines;
                    break;
                }
            }
        
        @endphp


        @foreach ($purposeLines as $line)
            <p style="text-align:center; margin:0; padding:0 2px 2px; font-size:{{ $purposeFontSize }}px; border-bottom:1px solid #000; min-height:{{ $purposeFontSize + 0.5 }}px;">
                {{ $line }}
            </p>
        @endforeach


    {{-- Per Reference Doc / UACS — same "short label, long wrapping value"
         shape as the header table, so the same treatment applies. --}}
    <table width="100%" style="border-collapse:collapse; margin-bottom:8px;">
        <tr>
            <td style="vertical-align:top; padding:3px 8px 3px 0; white-space:nowrap;">Per Reference Doc.</td>
            <td style="vertical-align:top; padding:3px 0; word-wrap:break-word; overflow-wrap:break-word;">
                {{ $formInput->staffInput->referenceDocument->original_filename ?? '' }}
                &nbsp;&nbsp;dated
                <span style="text-decoration:underline; padding:0 4px;">
                    {{ $formInput->staffInput->ref_date ? \Carbon\Carbon::parse($formInput->staffInput->ref_date)->format('j-M-Y') : '' }}
                </span>
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top; padding:3px 8px 3px 0; white-space:nowrap;">UACS</td>
            <td style="vertical-align:top; padding:3px 0; word-wrap:break-word; overflow-wrap:break-word;">
                {{ $formInput->staffInput->uacs->object_code ?? '' }} - {{ $formInput->staffInput->uacs->account_title ?? '' }}
            </td>
        </tr>
    </table>

    {{-- Bank Account table — deliberately UNCHANGED. This is a real tabular
         grid with column headers (No. / Name of Bank / Amount), not a
         label:value pair — table-layout:fixed + colgroup is the *correct*
         tool here, since you want every row's "Amount" column to line up
         vertically no matter what's in "Name of Bank" above or below it.
         Switching this one to auto layout would make columns drift per-row
         based on content length, which is wrong for a grid. --}}
    <p style="margin:8px 0 4px;">Please deposit the collections under Bank Account/s:</p>
    <table width="100%" border="1" style="border-collapse:collapse; margin-bottom:8px; table-layout:fixed;">
        <colgroup>
            <col style="width:25%;">
            <col style="width:45%;">
            <col style="width:30%;">
        </colgroup>
        <tr>
            <th style="padding:5px 4px; font-size:7.5px;">No.</th>
            <th style="padding:5px 4px; font-size:7.5px;">Name of Bank</th>
            <th style="padding:5px 4px; font-size:7.5px;">Amount</th>
        </tr>
        <tr>
            <td style="padding:5px 4px; text-align:center; word-wrap:break-word; overflow-wrap:break-word;">{{ $formInput->staffInput->bankAccount->account_num ?? 'N/A' }}</td>
            <td style="padding:5px 4px; text-align:center; word-wrap:break-word; overflow-wrap:break-word;">{{ $formInput->staffInput->bankAccount->bank_name ?? 'N/A' }}</td>
            <td style="padding:5px 4px; text-align:right;">₱{{ number_format($formInput->amount, 2) }}</td>
        </tr>
    </table>

    {{-- TOTAL — deliberately UNCHANGED. "TOTAL" is a fixed short label and
         the amount column just needs to stay right-aligned and reasonably
         wide; there's no long/variable content fighting for space here,
         so a fixed width causes no real problem. --}}
    <table width="100%" style="border-collapse:collapse; margin-bottom:12px;">
        <tr>
            <td style="text-align:right; padding:3px 6px 6px 0;"><strong>TOTAL</strong></td>
            <td style="width:35%; text-align:right; padding:3px 6px 6px; border-bottom:1px solid #000;">
                <strong>₱{{ number_format($formInput->amount, 2) }}</strong>
            </td>
        </tr>
    </table>

    {{-- Signature block — deliberately UNCHANGED. This is a symmetric
         50/50 page split (blank left, signature block right), not a
         content-driven pairing, so a fixed width is exactly right. --}}
    <table width="100%" style="border-collapse:collapse; margin-bottom:6px;">
        <tr>
            <td style="width:50%;"></td>
            <td style="width:50%; text-align:center;">
                <p style="margin:0 0 3px;">(SGD)</p>
                {{-- TODO: replace with the actual signed-in staff user's name
                     once request processing tracks who approved it. Hardcoded
                     for now to match the current authorized signatory. --}}
                <p style="margin:0 0 2px; text-decoration:underline; font-weight:bold;">
                    MAURICE ANAVER B. DORDADO, CPA
                </p>
                <p style="margin:0 0 1px;">Head of Accounting/Division/Unit</p>
                <p style="margin:0;">Authorized Official</p>
            </td>
        </tr>
    </table>

        @php
            $orNo = $formInput->staffInput?->or_no ?? '';
            $orDate = $formInput->staffInput?->or_date ? \Carbon\Carbon::parse($formInput->staffInput?->or_date)->format('F j, Y') : '';
        @endphp

        @if($orDate)
            <p style="margin:10px 0 0;"><strong>{{ $orDate }}</strong></p>
        @endif
        <p style="margin:2px 0 0;"><strong>OR No.</strong>: <span style="text-decoration:underline;">{{ $orNo ?: '______________________' }}</span></p>
</div>