<div style="position:relative; border:1px solid #000; padding:5px; font-family: sans-serif; font-size:8.5px; color:#000; height:165mm; box-sizing:border-box; width:100%;">

    <p style="text-align:right; font-size:7.5px; margin:0 0 4px;">{{ $copyLabel }}</p>

    <table width="100%" style="border-collapse:collapse; margin-bottom:4px;">
        <tr>
            <td style="width:65%; vertical-align:top; padding:0;">
                <strong>Entity Name:</strong>
                <span style="text-decoration:underline; font-weight:bold;">
                    NEGROS ORIENTAL STATE UNIVERSITY
                </span>
            </td>
            <td style="width:35%; vertical-align:top; padding:0; text-align:left;">
                <strong>Serial No.</strong> {{ $formInput->reference_number }}
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top; padding:2px 0 0;">
                <strong>Fund Cluster:</strong>
                <span style="border:1px solid #999; padding:1px 30px 1px 4px; display:inline-block;">
                    {{ $formInput->staffInput->bankAccount->fund_cluster ?? '' }}
                </span>
            </td>
            <td style="vertical-align:top; padding:2px 0 0;">
                <strong>Date:</strong>
                <span style="text-decoration:underline;">
                    {{ $formInput->staffInput->ref_date ? \Carbon\Carbon::parse($formInput->staffInput->ref_date)->format('F j, Y') : '' }}
                </span>
            </td>
        </tr>
    </table>

    <h2 style="text-align:center; margin:6px 0; font-size:12px; letter-spacing:0.5px;">ORDER OF PAYMENT</h2>

    <p style="margin:0;">The Collecting Officer</p>
    <p style="margin:0 0 6px;">Cash / Treasury Unit</p>

    <p style="text-align:center; margin:0 0 4px;">Please issue Official Receipt in favor of:</p>
    <p style="text-align:center; margin:0; font-weight:bold;">
        , {{ $formInput->firstname_or_office }} {{ $formInput->middlename_or_project }} {{ $formInput->lastname_or_agency }} ,
    </p>
    <p style="text-align:center; margin:2px 0; text-decoration:underline;">{{ $formInput->address }}</p>
    <p style="text-align:center; margin:0 0 6px; font-size:7px;">(Name and Address/ Office of Payor)</p>

    <table width="100%" style="border-collapse:collapse; margin-bottom:2px;">
        <tr>
            <td style="width:22%; vertical-align:top; padding:2px 0;">in the amount of</td>
            <td style="vertical-align:top; padding:2px 0; text-align:center; border-bottom:1px solid #000;">
                {{ strtoupper(numberToWords($formInput->amount)) }}
            </td>
        </tr>
        <tr>
            <td style="padding:1px 0;"></td>
            <td style="padding:1px 0; text-align:center; border-bottom:1px solid #000;">
                ₱{{ number_format($formInput->amount, 2) }}
            </td>
        </tr>
    </table>

    <p style="margin:4px 0 0;">for payment of</p>
    <p style="margin:0 0 4px; font-size:7px;">(Purpose)</p>
    <p style="text-align:center; margin:0 0 6px; padding:0 4px;">
        {{ $formInput->purpose ?? '' }}
    </p>

    <table width="100%" style="border-collapse:collapse; margin-bottom:4px;">
        <tr>
            <td style="width:22%; vertical-align:top; padding:2px 0;">Per Reference Doc.</td>
            <td style="vertical-align:top; padding:2px 0;">
                {{ $formInput->staffInput->referenceDocument->original_filename ?? '' }}
                &nbsp;&nbsp;dated
                <span style="text-decoration:underline;">
                    {{ $formInput->staffInput->ref_date ? \Carbon\Carbon::parse($formInput->staffInput->ref_date)->format('j-M-Y') : '' }}
                </span>
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top; padding:2px 0;">UACS</td>
            <td style="vertical-align:top; padding:2px 0;">
                {{ $formInput->staffInput->uacs->object_code ?? '' }} - {{ $formInput->staffInput->uacs->account_title ?? '' }}
            </td>
        </tr>
    </table>

    <p style="margin:4px 0 2px;">Please deposit the collections under Bank Account/s:</p>
    <table width="100%" border="1" style="border-collapse:collapse; margin-bottom:4px;">
        <tr>
            <th style="padding:2px; font-size:7.5px;">No.</th>
            <th style="padding:2px; font-size:7.5px;">Name of Bank</th>
            <th style="padding:2px; font-size:7.5px;">Amount</th>
        </tr>
        <tr>
            <td style="padding:2px; text-align:center;">{{ $formInput->staffInput->bankAccount->account_num ?? 'N/A' }}</td>
            <td style="padding:2px; text-align:center;">{{ $formInput->staffInput->bankAccount->bank_name ?? 'N/A' }}</td>
            <td style="padding:2px; text-align:right;">₱{{ number_format($formInput->amount, 2) }}</td>
        </tr>
    </table>

    <table width="100%" style="border-collapse:collapse; margin-bottom:8px;">
        <tr>
            <td style="text-align:right; padding:2px 0;"><strong>TOTAL</strong></td>
            <td style="width:35%; text-align:right; padding:2px 6px; border-bottom:1px solid #000;">
                <strong>₱{{ number_format($formInput->amount, 2) }}</strong>
            </td>
        </tr>
    </table>

    <table width="100%" style="border-collapse:collapse; margin-bottom:4px;">
        <tr>
            <td style="width:50%;"></td>
            <td style="width:50%; text-align:center;">
                <p style="margin:0;">(SGD)</p>
                {{-- TODO: replace with the actual signed-in staff user's name
                     once request processing tracks who approved it. Hardcoded
                     for now to match the current authorized signatory. --}}
                <p style="margin:0; text-decoration:underline; font-weight:bold;">
                    MAURICE ANAVER B. DORDADO, CPA
                </p>
                <p style="margin:0;">Head of Accounting/Division/Unit</p>
                <p style="margin:0;">Authorized Official</p>
            </td>
        </tr>
    </table>

        <p style="position:absolute; bottom:5px; left:5px; margin:0;">OR No.: ______________________</p>
</div>
</div>
</div>