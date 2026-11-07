@extends('layouts.app')

@section('title', 'Sign up')
@section('meta_description', 'Create your free Muallam account to start writing and sharing articles.')

@section('content')
    <section class="section auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card auth-card shadow-sm">
                        <div class="card-body p-4 p-md-5">
                            <h2 class="h3 mb-1">Create your account</h2>
                            <p class="text-secondary mb-4">Add your bio, photo and other details later from your profile.</p>

                            <form method="POST" action="{{ route('register.store') }}" novalidate
                                  x-data="registerForm(@js(['name' => old('name', ''), 'email' => old('email', '')]), @js(collect($errors->getMessages())->map(fn ($messages) => $messages[0])))"
                                  @submit="submit($event)">
                                @csrf

                                <div class="form-floating mb-1">
                                    <input type="text" id="name" name="name" class="form-control" placeholder="Full name"
                                           autocomplete="name" maxlength="100" required autofocus
                                           x-model="fields.name" @blur="touch('name')"
                                           :class="stateClass('name')"
                                           aria-describedby="name-error">
                                    <label for="name">Full name</label>
                                </div>
                                <div id="name-error" class="field-error mb-3" x-text="errors.name">{{ $errors->first('name') }}</div>

                                <div class="form-floating mb-1">
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Email"
                                           autocomplete="email" maxlength="255" required
                                           x-model="fields.email" @blur="touch('email')"
                                           :class="stateClass('email')"
                                           aria-describedby="email-error">
                                    <label for="email">Email</label>
                                </div>
                                <div id="email-error" class="field-error mb-3" x-text="errors.email">{{ $errors->first('email') }}</div>

                                <div class="position-relative mb-1">
                                    <div class="form-floating">
                                        <input :type="showPassword ? 'text' : 'password'" type="password" id="password" name="password"
                                               class="form-control has-toggle" placeholder="Password"
                                               autocomplete="new-password" maxlength="72" required
                                               x-model="fields.password" @blur="touch('password')"
                                               :class="stateClass('password')"
                                               aria-describedby="password-error password-strength">
                                        <label for="password">Password</label>
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
                                           name="password_confirmation" class="form-control" placeholder="Confirm password"
                                           autocomplete="new-password" maxlength="72" required
                                           x-model="fields.password_confirmation" @blur="touch('password_confirmation')"
                                           :class="stateClass('password_confirmation')"
                                           aria-describedby="password-confirmation-error">
                                    <label for="password_confirmation">Confirm password</label>
                                </div>
                                <div id="password-confirmation-error" class="field-error mb-4" x-text="errors.password_confirmation"></div>

                                <button type="submit" class="btn btn-accent btn-lg w-100" :disabled="submitting">
                                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                    Create account
                                </button>
                            </form>

                            @if (Route::has('login'))
                                <p class="text-center text-secondary mt-4 mb-0">
                                    Already have an account? <a href="{{ route('login') }}">Sign in</a>
                                </p>
                            @endif
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
            Alpine.data('registerForm', (old, serverErrors) => ({
                fields: { name: old.name, email: old.email, password: '', password_confirmation: '' },
                errors: { name: '', email: '', password: '', password_confirmation: '', ...serverErrors },
                touched: {},
                showPassword: false,
                submitting: false,

                init() {
                    // Fields that came back with a server error count as touched, so they re-check as you type.
                    Object.keys(serverErrors).forEach((field) => this.touched[field] = true);

                    Object.keys(this.fields).forEach((field) => {
                        this.$watch(`fields.${field}`, () => {
                            if (this.touched[field]) this.validate(field);
                            if (field === 'password' && this.touched.password_confirmation) this.validate('password_confirmation');
                        });
                    });
                },

                touch(field) {
                    this.touched[field] = true;
                    this.validate(field);
                },

                validate(field) {
                    const value = this.fields[field].trim();
                    const rules = {
                        name: () => {
                            if (!value) return 'Please enter your full name.';
                            if (value.length < 2) return 'The full name must be at least 2 characters.';
                            if (!/^[\p{L}\p{M}][\p{L}\p{M}\s.'-]*$/u.test(value)) return 'The name may only contain letters, spaces, dots, apostrophes and hyphens.';
                            return '';
                        },
                        email: () => {
                            if (!value) return 'Please enter your email.';
                            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Please enter a valid email address.';
                            return '';
                        },
                        password: () => {
                            if (!this.fields.password) return 'Please choose a password.';
                            if (this.fields.password.length < 8) return 'The password must be at least 8 characters.';
                            return '';
                        },
                        password_confirmation: () => {
                            if (!this.fields.password_confirmation) return 'Please confirm your password.';
                            if (this.fields.password_confirmation !== this.fields.password) return 'The passwords do not match.';
                            return '';
                        },
                    };

                    this.errors[field] = rules[field]();
                    return this.errors[field] === '';
                },

                stateClass(field) {
                    if (!this.touched[field]) return '';
                    return this.errors[field] ? 'is-invalid' : 'is-valid';
                },

                get strength() {
                    const password = this.fields.password;
                    let score = 0;
                    if (password.length >= 8) score++;
                    if (password.length >= 12) score++;
                    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
                    if (/\d/.test(password)) score++;
                    if (/[^A-Za-z0-9]/.test(password)) score++;

                    if (password.length < 8 || score <= 2) return { label: 'Weak', percent: 33, barClass: 'bg-danger', textClass: 'text-danger' };
                    if (score <= 3) return { label: 'Medium', percent: 66, barClass: 'bg-warning', textClass: 'text-warning-emphasis' };
                    return { label: 'Strong', percent: 100, barClass: 'bg-success', textClass: 'text-success' };
                },

                submit(event) {
                    const fields = Object.keys(this.fields);
                    fields.forEach((field) => this.touched[field] = true);
                    const valid = fields.map((field) => this.validate(field)).every(Boolean);

                    if (!valid) {
                        event.preventDefault();
                        document.getElementById(fields.find((field) => this.errors[field]))?.focus();
                        return;
                    }

                    this.submitting = true;
                },
            }));
        });
    </script>
@endpush
