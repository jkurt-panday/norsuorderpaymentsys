@php
    $official = activeAuthorizedOfficial();
    $signatoryName = $official?->name ?? 'MAURICE ANAVER B. DORDADO, CPA';
    $signatoryPosition = $official?->position ?? 'Head of Accounting/Division/Unit';
@endphp

<table class="op-table" style="margin-bottom:2px;">
    <tr>
        <td style="text-align:right;">
            <p style="margin:8px 0 1px;">(SGD)</p>
            <p style="margin:13px 0 1px; text-decoration:underline; font-weight:bold;">
                {{ $signatoryName }}
            </p>
            <p style="margin:0;">{{ $signatoryPosition }}</p>
            <p style="margin:0;">Authorized Official</p>
        </td>
    </tr>
</table>
