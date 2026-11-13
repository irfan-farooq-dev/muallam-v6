@props(['post'])

@php
    $badge = $post->isScheduled()
        ? ['label' => 'Scheduled', 'class' => 'text-bg-info', 'title' => 'Goes live '.$post->published_at->format('j M Y, g:i a')]
        : match ($post->status) {
            \App\Enums\PostStatus::Published => ['label' => 'Published', 'class' => 'text-bg-success', 'title' => null],
            \App\Enums\PostStatus::Draft => ['label' => 'Draft', 'class' => 'text-bg-secondary', 'title' => null],
            \App\Enums\PostStatus::Private => ['label' => 'Private', 'class' => 'text-bg-dark', 'title' => 'Only you and admins can see this'],
            \App\Enums\PostStatus::Restricted => ['label' => 'Restricted', 'class' => 'text-bg-warning', 'title' => 'Only invited people with the password'],
        };
@endphp

{{-- A scheduled post's go-live time is put in the reader's time zone by public/js/local-time.js. --}}
<span {{ $attributes->class(['badge', 'post-status-badge', $badge['class']]) }} @if ($badge['title']) title="{{ $badge['title'] }}" @endif
      @if ($post->isScheduled()) datetime="{{ $post->published_at->toIso8601String() }}" data-local-time="datetime" data-local-title data-local-title-prefix="Goes live " @endif>{{ $badge['label'] }}</span>
