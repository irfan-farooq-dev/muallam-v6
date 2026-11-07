{{-- Round profile photo, or the user's initials when they have none. Usage: <x-user-avatar :user="$user" :size="36" /> --}}
@props(['user', 'size' => 36])

@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" width="{{ $size }}" height="{{ $size }}"
         {{ $attributes->class(['user-avatar', 'rounded-circle']) }} style="width: {{ $size }}px; height: {{ $size }}px;">
@else
    <span {{ $attributes->class(['user-avatar', 'user-avatar-initials']) }}
          style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size * 0.4) }}px;"
          aria-hidden="true">{{ $user->initials() }}</span>
@endif
