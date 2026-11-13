@props(['at', 'format' => 'datetime', 'relative' => false])
{{--
    A date or time shown in the reader's own time zone. Times are stored in UTC; public/js/local-time.js rewrites
    the text (or, with "relative", the hover title) in the browser. The UTC text below is only the no-JavaScript fallback.
    Formats: datetime (14 Nov 2026, 1:12 am), date (14 Nov 2026), long-date (14 November 2026), month (November 2026),
    weekday-date (Saturday, 14 November 2026).
--}}
@php
    $pattern = [
        'datetime' => 'j M Y, g:i a',
        'date' => 'j M Y',
        'long-date' => 'j F Y',
        'month' => 'F Y',
        'weekday-date' => 'l, j F Y',
    ][$format] ?? 'j M Y, g:i a';
@endphp
<time datetime="{{ $at->toIso8601String() }}" data-local-time="{{ $relative ? 'datetime' : $format }}" @if ($relative) data-local-title title="{{ $at->format('j M Y, g:i a') }}" @endif {{ $attributes }}>{{ $relative ? $at->diffForHumans() : $at->format($pattern) }}</time>
