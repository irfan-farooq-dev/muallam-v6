@extends('layouts.app')

@section('title', $category->name)
@section('meta_description', $category->description ?: "Muallam articles about {$category->name}.")

@section('content')
    <section class="section">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Categories</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
                </ol>
            </nav>
            <h2 class="h3 mb-1">{{ $category->name }}</h2>
            @if ($category->description)
                <p class="text-secondary mb-4">{{ $category->description }}</p>
            @endif

            @if ($posts->isEmpty())
                <div class="empty-state text-center text-secondary py-5 mt-4">
                    <i class="bi bi-journal-text display-6 d-block mb-3"></i>
                    No posts in {{ $category->name }} yet.
                </div>
            @else
                <div class="row g-3 mt-1">
                    @foreach ($posts as $post)
                        <div class="col-md-6 col-lg-4">
                            @include('partials.post-card', ['post' => $post])
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
