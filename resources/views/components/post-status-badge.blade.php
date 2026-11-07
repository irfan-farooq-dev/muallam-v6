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

<span {{ $attributes->class(['badge', $badge['class']]) }} @if ($badge['title']) title="{{ $badge['title'] }}" @endif>{{ $badge['label'] }}</span>
