@extends('layouts.app')

@section('title', 'Sign in')
@section('meta_description', 'Sign in to your Muallam account.')

@section('content')
    <section class="section auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card auth-card shadow-sm">
                        <div class="card-body p-4 p-md-5">
                            <h2 class="h3 mb-1">Welcome back</h2>
                            <p class="text-secondary mb-4">Sign in to continue to {{ config('app.name') }}.</p>

                            <form method="POST" action="{{ route('login.store') }}" novalidate
                                  x-data="loginForm(@js(old('email', '')), @js(collect($errors->getMessages())->map(fn ($messages) => $messages[0])))"
                                  @submit="submit($event)">
                                @csrf

                                <div class="form-floating mb-1">
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Email"
                                           autocomplete="username" maxlength="255" required autofocus
                                           x-model="fields.email" @input="errors.email = ''"
                                           :class="{ 'is-invalid': errors.email }"
                                           aria-describedby="email-error">
                                    <label for="email">Email</label>
                                </div>
                                <div id="email-error" class="field-error mb-3" x-text="errors.email">{{ $errors->first('email') }}</div>

                                <div class="position-relative mb-1">
                                    <div class="form-floating">
                                        <input :type="showPassword ? 'text' : 'password'" type="password" id="password" name="password"
                                               class="form-control has-toggle" placeholder="Password"
                                               autocomplete="current-password" maxlength="255" required
                                               x-model="fields.password" @input="errors.password = ''"
                                               :class="{ 'is-invalid': errors.password }"
                                               aria-describedby="password-error">
                                        <label for="password">Password</label>
                                    </div>
                                    <button type="button" class="btn btn-link password-toggle" @click="showPassword = !showPassword"
                                            :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                        <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                                <div id="password-error" class="field-error mb-3" x-text="errors.password">{{ $errors->first('password') }}</div>

                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
                                        <label class="form-check-label" for="remember">Remember me</label>
                                    </div>
                                    @if (Route::has('password.request'))
                                        <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
                                    @endif
                                </div>

                                <button type="submit" class="btn btn-accent btn-lg w-100" :disabled="submitting">
                                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                    Sign in
                                </button>
                            </form>

                            <p class="text-center text-secondary mt-4 mb-0">
                                New to {{ config('app.name') }}? <a href="{{ route('register') }}">Create an account</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('loginForm', (oldEmail, serverErrors) => ({
                fields: { email: oldEmail, password: '' },
                errors: { email: '', password: '', ...serverErrors },
                showPassword: false,
                submitting: false,

                submit(event) {
                    const email = this.fields.email.trim();
                    this.errors.email = !email ? 'Please enter your email.'
                        : (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? 'Please enter a valid email address.' : '');
                    this.errors.password = this.fields.password ? '' : 'Please enter your password.';

                    const firstInvalid = ['email', 'password'].find((field) => this.errors[field]);
                    if (firstInvalid) {
                        event.preventDefault();
                        document.getElementById(firstInvalid).focus();
                        return;
                    }

                    this.submitting = true;
                },
            }));
        });
    </script>
@endpush
