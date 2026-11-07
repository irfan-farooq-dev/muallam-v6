{{-- Shared body for error pages: @include('errors.message', ['code' => ..., 'heading' => ..., 'text' => ...]) --}}
<section class="error-page">
    <div class="container text-center">
        <div class="error-code mb-3">{{ $code }}</div>
        <h2 class="mb-3">{{ $heading }}</h2>
        <p class="lead text-secondary mb-4">{{ $text }}</p>
        <a href="{{ route('home') }}" class="btn btn-accent px-4">Back to home</a>
    </div>
</section>
