{{-- EduVers email header: logo, wordmark and motto (overrides Laravel's default mail header). --}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim((string) config('app.url'), '/') }}/images/logo.jpg" class="logo" width="64" height="64" alt="">
<span class="brand">{{ trim($slot) === 'Laravel' ? 'EduVers' : $slot }}</span>
</a>
<p class="motto">Transform · Learn · Lead</p>
</td>
</tr>
