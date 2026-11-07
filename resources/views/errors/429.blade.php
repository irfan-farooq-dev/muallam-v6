@extends('layouts.app')

@section('title', 'Too many attempts')

@php
    $retryAfter = (int) ($exception->getHeaders()['Retry-After'] ?? 60);
@endphp

@section('content')
    <section class="error-page">
        <div class="container text-center" x-data="{ seconds: {{ $retryAfter }} }"
             x-init="const timer = setInterval(() => { if (seconds > 0) { seconds-- } else { clearInterval(timer) } }, 1000)">
            <div class="error-code mb-3">429</div>
            <h2 class="mb-3">Too many attempts</h2>
            <p class="lead text-secondary mb-4" x-show="seconds > 0">
                Please wait <strong x-text="seconds">{{ $retryAfter }}</strong> seconds before trying again.
            </p>
            <p class="lead text-secondary mb-4" x-show="seconds === 0" x-cloak>You can try again now.</p>
            <a href="{{ url()->previous() }}" class="btn btn-outline-accent px-4 me-2" x-show="seconds === 0" x-cloak>Try again</a>
            <a href="{{ route('home') }}" class="btn btn-accent px-4">Back to home</a>
        </div>
    </section>
@endsection
