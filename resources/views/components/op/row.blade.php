@props([
    'labelWidth' => '16%',
    'valign' => 'top',
    'align' => 'left',
    'borderBottom' => false,
    'pb' => 1,
])
<tr>
    <td style="width: {{ $labelWidth }}; vertical-align: {{ $valign }}; padding: 1px 0 {{ $pb }}px;">
        {{ $label ?? '' }}
    </td>
    <td style="vertical-align: {{ $valign }}; padding: 1px 0 {{ $pb }}px; text-align: {{ $align }};{{ $borderBottom ? ' border-bottom:1px solid #000;' : '' }}">
        {{ $slot }}
    </td>
</tr>