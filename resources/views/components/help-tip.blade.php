@props(['text'])
{{-- A small "?" that explains an option on hover, focus or tap (Bootstrap tooltip, started in layouts/app). --}}
<button type="button" {{ $attributes->class('help-tip') }} data-bs-toggle="tooltip" data-bs-title="{{ $text }}" aria-label="{{ $text }}">
    <i class="bi bi-question-circle" aria-hidden="true"></i>
</button>
