{{--
    Email for new lessons, quizzes and announcements (App\Notifications\StudentActivityNotification).
    Markdown mail: keep lines unindented, or Markdown turns them into code blocks.
    Teacher-written values arrive already Markdown-escaped; {{ }} escapes HTML.
--}}
<x-mail::message>
# {{ $heading }}

Hi {{ $firstName }},

{{ $intro }}

<x-mail::panel>
**{{ $title }}**

@if (filled($excerpt))
{{ $excerpt }}
@endif
</x-mail::panel>

@if (! empty($details))
<x-mail::table>
| | |
|:--|:--|
@foreach ($details as $label => $value)
| **{{ $label }}** | {{ $value }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="$url" color="primary">
{{ $action }}
</x-mail::button>

Keep learning,<br>
**EduVers** · Movers Institute of Technology and Education

<x-slot:subcopy>
If the button doesn't work, copy this link into your browser: <span class="break-all">[{{ $url }}]({{ $url }})</span><br>
You're getting this email because you're enrolled in EduVers. You'll also find it under the bell in your dashboard.
</x-slot:subcopy>
</x-mail::message>
