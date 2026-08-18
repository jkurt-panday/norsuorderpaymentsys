{{-- resources/views/pdf/op-landscape.blade.php --}}
<html>
<head>
<style>
    @page { margin: 5mm; }
    body { margin: 0; }

    table.op-row {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    table.op-row td.copy {
        width: 32%;
        vertical-align: top;
        padding: 0 6px;
        box-sizing: border-box;
    }

    table.op-row td.cut {
        width: 1.5%;
        vertical-align: top;
    }

    /* Landscape-only fix for the Entity/Serial/Fund Cluster/Date header table.
       Scoped to .copy so it never touches the portrait render of op-copy.blade.php. */
    table.op-row td.copy > table:first-of-type td {
        font-size: 7.5px !important;
    }

    table.op-row td.copy > table:first-of-type td:nth-child(1) {
        width: 55% !important;
    }

    table.op-row td.copy > table:first-of-type td:nth-child(2) {
        width: 45% !important;
    }
</style>
</head>
<body>
    <table class="op-row">
        <tr>
            @foreach ($copyLabels as $copyLabel)
                <td class="copy">
                    @include('pdf.op-copy', ['formInput' => $formInput, 'copyLabel' => $copyLabel])
                </td>
                @if (!$loop->last)
                    <td class="cut"></td>
                @endif
            @endforeach
        </tr>
    </table>
</body>
</html>