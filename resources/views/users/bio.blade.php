@extends('layouts.app')

@section('title', $profileUser->name)
@section('meta_description', \Illuminate\Support\Str::limit($profileUser->bio ?? "{$profileUser->name} on Muallam", 155))

@section('og_type', 'profile')
@if ($profileUser->avatarUrl())
    @section('og_image', $profileUser->avatarUrl())
@endif
@unless ($profileUser->is_bio_public)
    @section('robots', 'noindex, nofollow')
@endunless

@section('content')
    <section class="section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <x-breadcrumbs class="mb-3" :items="[[$profileUser->name, route('users.posts', $profileUser->slug)], ['About']]" />
                    @if ($isOwner && (! $profileUser->is_bio_public || ! $profileUser->is_active))
                        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span><i class="bi bi-eye-slash me-1"></i>Only you can see this page. Your bio is hidden from everyone else.</span>
                            @if (Route::has('profile.edit'))
                                <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-accent">Edit profile</a>
                            @endif
                        </div>
                    @endif

                    <div class="card settings-card">
                        <div class="card-body p-4 p-md-5">
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <x-user-avatar :user="$profileUser" :size="88" />
                                <div>
                                    <h2 class="h3 mb-1">{{ $profileUser->name }}</h2>
                                    <div class="text-secondary small">
                                        Member since {{ $profileUser->created_at->format('F Y') }}
                                        &middot; <a href="{{ route('users.posts', $profileUser->slug) }}">Posts by {{ $profileUser->name }}</a>
                                    </div>
                                </div>
                            </div>

                            @if ($profileUser->bio)
                                <div class="profile-bio">{!! nl2br(e($profileUser->bio)) !!}</div>
                            @else
                                <p class="text-secondary fst-italic mb-0">{{ $profileUser->name }} hasn't written a bio yet.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
