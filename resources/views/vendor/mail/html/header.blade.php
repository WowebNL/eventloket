@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{!! file_get_contents(public_path('images/logos/logo-dark.svg')) !!}
</a>
</td>
</tr>
