{{-- resources/views/pdf/op-portrait.blade.php --}}
<html>
<head>
<style>
    @page { size: a5; margin: 8mm; }
    body { margin: 0; }
    .op-page { page-break-after: always; }
    .op-page:last-child { page-break-after: avoid; }
</style>
</head>
<body>
    @foreach ($copyLabels as $copyLabel)
        <div class="op-page">
            @include('pdf.op-copy', ['formInput' => $formInput, 'copyLabel' => $copyLabel])
        </div>
    @endforeach
</body>
</html>