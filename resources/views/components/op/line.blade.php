@props([
    'align' => 'left',
    'bold' => false,
    'underline' => false,
    'size' => null,
    'pb' => 1,
])
<tr>
    <td colspan="2" style="text-align: {{ $align }}; padding: 1px 0 {{ $pb }}px;{{ $bold ? ' font-weight:bold;' : '' }}{{ $underline ? ' text-decoration:underline;' : '' }}{{ $size ? ' font-size:'.$size.';' : '' }}">
        {{ $slot }}
    </td>
</tr>