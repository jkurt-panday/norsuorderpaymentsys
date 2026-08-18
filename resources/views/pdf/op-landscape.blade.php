{{-- resources/views/pdf/op-landscape.blade.php --}}
<html>
<head>
<style>
    @page { margin: 6mm; }
    body { margin: 0; }
    table.op-row { width: 100%; border-collapse: collapse; }
    table.op-row td { width: 33.33%; vertical-align: top; padding: 0 3px; }
</style>
</head>
<body>
    <table class="op-row">
        <tr>
            @foreach ($copyLabels as $copyLabel)
                <td>
                    @include('pdf.op-copy', ['formInput' => $formInput, 'copyLabel' => $copyLabel])
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>