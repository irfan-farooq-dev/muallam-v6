@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
    <section class="section auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card auth-card shadow-sm">
                        <div class="card-body p-4 p-md-5 text-center">
                            <i class="bi bi-envelope-check display-5 text-secondary d-block mb-3"></i>
                            <h2 class="h3 mb-3">Check your inbox</h2>
                            <p class="text-secondary mb-4">
                                We sent a verification link to <strong>{{ auth()->user()->email }}</strong>.
                                Click the link in that email to verify your address. It expires in {{ config('auth.verification.expire', 60) }} minutes.
                            </p>

                            <form method="POST" action="{{ route('verification.send') }}" x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                <button type="submit" class="btn btn-accent w-100" :disabled="submitting">
                                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                    Resend verification email
                                </button>
                            </form>

                            <p class="text-secondary small mt-4 mb-0">
                                Can't find it? Check your spam folder. Signed up with the wrong email?
                                <a href="#" onclick="event.preventDefault(); document.getElementById('verify-logout-form').submit();">Log out</a>
                                and create a new account.
                            </p>
                            <form id="verify-logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
