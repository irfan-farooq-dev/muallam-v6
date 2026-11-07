{{-- Plain text with removed words struck through and added words highlighted. Expects $segments. Kept on one line so no extra spaces appear between words. --}}
@php
    $wrappers = ['added' => 'ins class="diff-added"', 'removed' => 'del class="diff-removed"'];
    $html = collect($segments)->map(function (array $segment) use ($wrappers): string {
        $text = e($segment['text']);

        return isset($wrappers[$segment['type']])
            ? '<'.$wrappers[$segment['type']].'>'.$text.'</'.strtok($wrappers[$segment['type']], ' ').'>'
            : $text;
    })->implode('');
@endphp
{!! $html !!}
