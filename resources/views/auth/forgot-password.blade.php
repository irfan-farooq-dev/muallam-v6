@extends('layouts.app')

@section('title', 'Forgot password')

@section('content')
    <section class="section auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card auth-card shadow-sm">
                        <div class="card-body p-4 p-md-5">
                            <h2 class="h3 mb-1">Forgot your password?</h2>
                            <p class="text-secondary mb-4">Enter the email you signed up with and we'll send you a link to choose a new password.</p>

                            <form method="POST" action="{{ route('password.email') }}" novalidate
                                  x-data="authForm(@js(['email' => old('email', '')]), @js(collect($errors->getMessages())->map(fn ($messages) => $messages[0])))"
                                  @submit="submit($event)">
                                @csrf

                                <div class="form-floating mb-1">
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Email"
                                           autocomplete="email" maxlength="255" required autofocus
                                           x-model="fields.email" @blur="touch('email')" :class="stateClass('email')"
                                           aria-describedby="email-error">
                                    <label for="email">Email</label>
                                </div>
                                <div id="email-error" class="field-error mb-4" x-text="errors.email">{{ $errors->first('email') }}</div>

                                <button type="submit" class="btn btn-accent btn-lg w-100" :disabled="submitting">
                                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                    Send reset link
                                </button>
                            </form>

                            <p class="text-center text-secondary mt-4 mb-0">
                                Remembered it? <a href="{{ route('login') }}">Back to sign in</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/auth-form.js') }}"></script>
@endpush
