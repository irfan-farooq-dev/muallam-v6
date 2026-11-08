{{--
    Round profile photo, or the user's initials when they have none. Usage: <x-user-avatar :user="$user" :size="36" />
    Pass :online="true" to add the green "online now" dot (pages decide, e.g. with $user->appearsOnlineTo(auth()->user())).
--}}
@props(['user', 'size' => 36, 'online' => false])

@php
    $dotSize = max(8, (int) round($size * 0.28));
@endphp

@if ($online)
    <span class="user-avatar-presence" style="width: {{ $size }}px; height: {{ $size }}px;">
@endif

@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" width="{{ $size }}" height="{{ $size }}"
         {{ $attributes->class(['user-avatar', 'rounded-circle']) }} style="width: {{ $size }}px; height: {{ $size }}px;">
@else
    <span {{ $attributes->class(['user-avatar', 'user-avatar-initials']) }}
          style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size * 0.4) }}px;"
          aria-hidden="true">{{ $user->initials() }}</span>
@endif

@if ($online)
        <span class="online-dot" style="width: {{ $dotSize }}px; height: {{ $dotSize }}px;" title="Online now">
            <span class="visually-hidden">{{ $user->name }} is online now</span>
        </span>
    </span>
@endif
