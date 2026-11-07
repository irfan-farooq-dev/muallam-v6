@extends('layouts.member')

@section('title', 'Liked posts')

@section('member_content')
    @if ($posts->isEmpty())
        <div class="empty-state text-center text-secondary py-5">
            <i class="bi bi-hand-thumbs-up display-6 d-block mb-3"></i>
            Posts you like will appear here.
            <div class="mt-3"><a href="{{ route('home') }}" class="btn btn-outline-accent">Find something to read</a></div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($posts as $post)
                <div class="col-md-6">
                    @include('partials.post-card', ['post' => $post])
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
@endsection
