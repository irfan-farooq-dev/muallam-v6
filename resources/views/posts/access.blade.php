@extends('layouts.app')

@section('title', 'Enter the post password')

@push('styles')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <section class="section auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card auth-card shadow-sm">
                        <div class="card-body p-4 p-md-5">
                            <div class="text-center mb-4">
                                <i class="bi bi-shield-lock display-6 text-secondary d-block mb-3"></i>
                                <h2 class="h4 mb-2">{{ $post->title }}</h2>
                                <p class="text-secondary small mb-0">
                                    {{ $post->user->name }} shared this post with you. Enter the password they gave you to read it.
                                </p>
                            </div>

                            <form method="POST" action="{{ route('posts.access.unlock', $post) }}" x-data="{ show: false, submitting: false }" @submit="submitting = true">
                                @csrf
                                <div class="position-relative mb-1">
                                    <div class="form-floating">
                                        <input :type="show ? 'text' : 'password'" type="password" id="password" name="password" required autofocus
                                               autocomplete="off" placeholder="Post password"
                                               @class(['form-control has-toggle', 'is-invalid' => $errors->access->has('password')])>
                                        <label for="password">Post password</label>
                                    </div>
                                    <button type="button" class="btn btn-link password-toggle" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'">
                                        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                                <div class="field-error mb-3">{{ $errors->access->first('password') }}</div>

                                <button type="submit" class="btn btn-accent btn-lg w-100" :disabled="submitting">Open the post</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
