@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
    @if (trim($slot) === 'Laravel')
        <img src="{{ asset('img/mainLogo.png') }}" class="logo" alt="App Logo">
    @else
        {{ $slot }}
    @endif
</a>
</td>
</tr>
