{{-- One-time messages set with ->with('success'|'error'|'warning'|'info', '...') or session('status'). --}}
@php
    $messages = collect([
        'success' => session('success') ?? session('status'),
        'danger' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ])->filter();
@endphp

@if ($messages->isNotEmpty())
    <div class="flash-messages pt-3">
        @foreach ($messages as $type => $message)
            <div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
                {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endforeach
    </div>
@endif
