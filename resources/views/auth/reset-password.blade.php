@extends('layouts.app')

@section('title', 'Choose a new password')

@section('content')
    <section class="section auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card auth-card shadow-sm">
                        <div class="card-body p-4 p-md-5">
                            <h2 class="h3 mb-1">Choose a new password</h2>
                            <p class="text-secondary mb-4">You'll be signed out on all devices and can then sign in with the new password.</p>

                            <form method="POST" action="{{ route('password.store') }}" novalidate
                                  x-data="authForm(@js(['email' => old('email', $email), 'password' => '', 'password_confirmation' => '']), @js(collect($errors->getMessages())->map(fn ($messages) => $messages[0])))"
                                  @submit="submit($event)">
                                @csrf
                                <input type="hidden" name="token" value="{{ $token }}">

                                <div class="form-floating mb-1">
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Email"
                                           autocomplete="username" maxlength="255" required
                                           x-model="fields.email" @blur="touch('email')" :class="stateClass('email')"
                                           aria-describedby="email-error">
                                    <label for="email">Email</label>
                                </div>
                                <div id="email-error" class="field-error mb-3" x-text="errors.email">{{ $errors->first('email') }}</div>

                                <div class="position-relative mb-1">
                                    <div class="form-floating">
                                        <input :type="showPassword ? 'text' : 'password'" type="password" id="password" name="password"
                                               class="form-control has-toggle" placeholder="New password"
                                               autocomplete="new-password" maxlength="72" required autofocus
                                               x-model="fields.password" @blur="touch('password')" :class="stateClass('password')"
                                               aria-describedby="password-error password-strength">
                                        <label for="password">New password</label>
                                    </div>
                                    <button type="button" class="btn btn-link password-toggle" @click="showPassword = !showPassword"
                                            :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                        <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                                <div id="password-strength" class="password-strength mb-1" x-show="fields.password.length > 0" x-cloak>
                                    <div class="progress" role="progressbar" aria-label="Password strength">
                                        <div class="progress-bar" :class="strength.barClass" :style="`width: ${strength.percent}%`"></div>
                                    </div>
                                    <small class="text-secondary">Strength: <span :class="strength.textClass" x-text="strength.label"></span></small>
                                </div>
                                <div id="password-error" class="field-error mb-3" x-text="errors.password">{{ $errors->first('password') }}</div>

                                <div class="form-floating mb-1">
                                    <input :type="showPassword ? 'text' : 'password'" type="password" id="password_confirmation"
                                           name="password_confirmation" class="form-control" placeholder="Confirm new password"
                                           autocomplete="new-password" maxlength="72" required
                                           x-model="fields.password_confirmation" @blur="touch('password_confirmation')"
                                           :class="stateClass('password_confirmation')"
                                           aria-describedby="password-confirmation-error">
                                    <label for="password_confirmation">Confirm new password</label>
                                </div>
                                <div id="password-confirmation-error" class="field-error mb-4" x-text="errors.password_confirmation"></div>

                                <button type="submit" class="btn btn-accent btn-lg w-100" :disabled="submitting">
                                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                    Change password
                                </button>
                            </form>

                            @if ($errors->has('email'))
                                <p class="text-center mt-4 mb-0"><a href="{{ route('password.request') }}">Request a new reset link</a></p>
                            @endif
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
