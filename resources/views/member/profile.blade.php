@extends('layouts.member')

@section('title', 'Profile')

@section('member_content')
    @php
        $detailErrors = collect($errors->getMessages())->map(fn ($messages) => $messages[0]);
        $passwordErrors = collect($errors->updatePassword->getMessages())->map(fn ($messages) => $messages[0]);
    @endphp

    {{-- Profile photo --}}
    <div class="card settings-card mb-4">
        <div class="card-body p-4">
            <h3 class="h5 mb-1">Profile photo</h3>
            <p class="text-secondary small mb-3">JPG, PNG or WebP, up to 2 MB. It's cropped to a square.</p>

            <div class="d-flex flex-wrap align-items-center gap-4"
                 x-data="{ preview: null, choose(event) { const file = event.target.files[0]; this.preview = file ? URL.createObjectURL(file) : null; } }">
                <div class="profile-photo-preview">
                    <img x-show="preview" x-cloak :src="preview" alt="New photo preview" class="rounded-circle user-avatar" width="96" height="96" style="width: 96px; height: 96px;">
                    <div x-show="!preview">
                        <x-user-avatar :user="$user" :size="96" />
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                        @csrf
                        <label class="btn btn-outline-accent mb-0">
                            <i class="bi bi-upload me-1"></i>Choose photo
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="d-none" @change="choose($event)">
                        </label>
                        <button type="submit" class="btn btn-accent" x-show="preview" x-cloak>Save photo</button>
                    </form>

                    @if ($user->avatar)
                        <form method="POST" action="{{ route('profile.photo.destroy') }}" x-show="!preview">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger">Remove photo</button>
                        </form>
                    @endif
                </div>
            </div>
            @if ($errors->updatePhoto->any())
                <div class="field-error mt-2">{{ $errors->updatePhoto->first('photo') }}</div>
            @endif
        </div>
    </div>

    {{-- Public profile link --}}
    <div class="card settings-card mb-4">
        <div class="card-body p-4">
            <h3 class="h5 mb-1">Public profile link</h3>
            <p class="text-secondary small mb-3">
                Your bio page lives at this address. Old links keep redirecting after a change, and you can change it once every {{ config('muallam.slug_change_cooldown_days') }} days.
            </p>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <code class="profile-link text-break">{{ route('users.bio', $user->slug) }}</code>
                <a href="{{ route('users.bio', $user->slug) }}" class="btn btn-sm btn-outline-accent" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right me-1"></i>View
                </a>
                <button type="button" class="btn btn-sm btn-outline-accent"
                        x-data="{ copied: false }"
                        @click="navigator.clipboard.writeText(@js(route('users.bio', $user->slug))); copied = true; setTimeout(() => copied = false, 1500)">
                    <i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i>
                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                </button>
            </div>
            @unless ($user->is_bio_public)
                <p class="small text-warning-emphasis mb-3"><i class="bi bi-eye-slash me-1"></i>Your bio is hidden, so only you can open this page. Turn on "Show my bio" below to make it public.</p>
            @endunless

            @if ($nextSlugChange)
                <p class="small text-secondary mb-0"><i class="bi bi-clock me-1"></i>You can change your link again on {{ $nextSlugChange->format('j M Y') }}.</p>
            @else
                <form method="POST" action="{{ route('profile.slug.update') }}"
                      x-data="slugForm(@js(['current' => $user->slug, 'initial' => old('slug', $user->slug), 'checkUrl' => route('profile.slug.check'), 'serverError' => $errors->updateSlug->first('slug')]))"
                      @submit="if (!canSave) $event.preventDefault()">
                    @csrf
                    @method('PUT')

                    <div class="input-group">
                        <span class="input-group-text d-none d-md-flex">{{ url('users') }}/</span>
                        <input type="text" name="slug" class="form-control" maxlength="100" autocomplete="off" spellcheck="false"
                               aria-label="New profile link" x-model="slug" @input.debounce.400ms="check()"
                               :class="{ 'is-valid': status === 'available', 'is-invalid': status === 'unavailable' }">
                        <span class="input-group-text d-none d-md-flex">/bio</span>
                        <button type="submit" class="btn btn-accent" :disabled="!canSave">Save link</button>
                    </div>
                    <div class="small mt-1" aria-live="polite">
                        <span x-show="status === 'checking'" class="text-secondary">Checking…</span>
                        <span x-show="status === 'available'" class="text-success"><i class="bi bi-check-circle me-1"></i>Available: <span x-text="preview"></span></span>
                        <span x-show="status === 'unavailable'" class="text-danger" x-text="message"></span>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- Personal details --}}
    <div class="card settings-card mb-4">
        <div class="card-body p-4">
            <h3 class="h5 mb-3">Personal details</h3>

            <form method="POST" action="{{ route('profile.update') }}" novalidate
                  x-data="{ email: @js(old('email', $user->email)), originalEmail: @js($user->email), bio: @js(old('bio', $user->bio ?? '')), bioMax: {{ \App\Http\Requests\Profile\UpdateProfileRequest::BIO_MAX }} }">
                @csrf
                @method('PATCH')

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" id="name" name="name" @class(['form-control', 'is-invalid' => $detailErrors->has('name')])
                                   placeholder="Full name" maxlength="100" required autocomplete="name"
                                   value="{{ old('name', $user->name) }}">
                            <label for="name">Full name</label>
                        </div>
                        <div class="field-error">{{ $detailErrors->get('name') }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="email" id="email" name="email" @class(['form-control', 'is-invalid' => $detailErrors->has('email')])
                                   placeholder="Email" maxlength="255" required autocomplete="email" x-model="email">
                            <label for="email">Email</label>
                        </div>
                        <div class="field-error">{{ $detailErrors->get('email') }}</div>
                    </div>

                    <div class="col-12" x-show="email.trim().toLowerCase() !== originalEmail" x-cloak>
                        <div class="alert alert-warning small mb-2">
                            Changing your email means verifying the new address. Enter your current password to confirm.
                        </div>
                        <div class="form-floating">
                            <input type="password" id="profile_current_password" name="current_password"
                                   @class(['form-control', 'is-invalid' => $detailErrors->has('current_password')])
                                   placeholder="Current password" autocomplete="current-password">
                            <label for="profile_current_password">Current password</label>
                        </div>
                        <div class="field-error">{{ $detailErrors->get('current_password') }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="tel" id="phone" name="phone" @class(['form-control', 'is-invalid' => $detailErrors->has('phone')])
                                   placeholder="Phone" maxlength="20" autocomplete="tel" value="{{ old('phone', $user->phone) }}">
                            <label for="phone">Phone <span class="text-secondary">(optional)</span></label>
                        </div>
                        <div class="field-error">{{ $detailErrors->get('phone') }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <select id="gender" name="gender" @class(['form-select', 'is-invalid' => $detailErrors->has('gender')])>
                                <option value="">Not specified</option>
                                @foreach ($genders as $gender)
                                    <option value="{{ $gender->value }}" @selected(old('gender', $user->gender?->value) === $gender->value)>{{ $gender->label() }}</option>
                                @endforeach
                            </select>
                            <label for="gender">Gender <span class="text-secondary">(optional)</span></label>
                        </div>
                        <div class="field-error">{{ $detailErrors->get('gender') }}</div>
                    </div>

                    <div class="col-12">
                        <div class="form-floating">
                            <textarea id="bio" name="bio" @class(['form-control', 'is-invalid' => $detailErrors->has('bio')])
                                      placeholder="Bio" style="height: 140px" :maxlength="bioMax" x-model="bio"></textarea>
                            <label for="bio">Bio <span class="text-secondary">(optional)</span></label>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div class="field-error">{{ $detailErrors->get('bio') }}</div>
                            <small class="text-secondary" :class="{ 'text-danger': bio.length > bioMax - 50 }"><span x-text="bio.length"></span> / {{ \App\Http\Requests\Profile\UpdateProfileRequest::BIO_MAX }}</small>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_bio_public" name="is_bio_public" value="1"
                                   @checked(old('is_bio_public', $user->is_bio_public))>
                            <label class="form-check-label" for="is_bio_public">Show my bio on my public profile page</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-accent mt-4">Save details</button>
            </form>
        </div>
    </div>

    {{-- Change password --}}
    <div class="card settings-card">
        <div class="card-body p-4">
            <h3 class="h5 mb-1">Change password</h3>
            <p class="text-secondary small mb-3">You'll stay signed in here and be signed out on your other devices.</p>

            <form method="POST" action="{{ route('profile.password.update') }}" novalidate
                  x-data="authForm({ current_password: '', password: '', password_confirmation: '' }, @js($passwordErrors))"
                  @submit="submit($event)">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-floating">
                            <input :type="showPassword ? 'text' : 'password'" type="password" id="current_password" name="current_password"
                                   class="form-control" placeholder="Current password" autocomplete="current-password"
                                   x-model="fields.current_password" @blur="touch('current_password')" :class="stateClass('current_password')">
                            <label for="current_password">Current password</label>
                        </div>
                        <div class="field-error" x-text="errors.current_password">{{ $passwordErrors->get('current_password') }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <input :type="showPassword ? 'text' : 'password'" type="password" id="password" name="password"
                                   class="form-control" placeholder="New password" autocomplete="new-password" maxlength="72"
                                   x-model="fields.password" @blur="touch('password')" :class="stateClass('password')">
                            <label for="password">New password</label>
                        </div>
                        <div class="password-strength mt-1" x-show="fields.password.length > 0" x-cloak>
                            <div class="progress" role="progressbar" aria-label="Password strength">
                                <div class="progress-bar" :class="strength.barClass" :style="`width: ${strength.percent}%`"></div>
                            </div>
                            <small class="text-secondary">Strength: <span :class="strength.textClass" x-text="strength.label"></span></small>
                        </div>
                        <div class="field-error" x-text="errors.password">{{ $passwordErrors->get('password') }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <input :type="showPassword ? 'text' : 'password'" type="password" id="password_confirmation" name="password_confirmation"
                                   class="form-control" placeholder="Confirm new password" autocomplete="new-password" maxlength="72"
                                   x-model="fields.password_confirmation" @blur="touch('password_confirmation')" :class="stateClass('password_confirmation')">
                            <label for="password_confirmation">Confirm new password</label>
                        </div>
                        <div class="field-error" x-text="errors.password_confirmation"></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-3 mt-4">
                    <button type="submit" class="btn btn-accent" :disabled="submitting">Change password</button>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="show_passwords" x-model="showPassword">
                        <label class="form-check-label" for="show_passwords">Show passwords</label>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/auth-form.js') }}"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('slugForm', ({ current, initial, checkUrl, serverError }) => ({
                slug: initial,
                status: serverError ? 'unavailable' : 'idle',
                message: serverError,
                preview: '',
                checked: null,
                request: 0,

                get canSave() {
                    return this.status === 'available' && this.checked === this.slug;
                },

                async check() {
                    const value = this.slug.trim();
                    if (!value || value === current) {
                        this.status = 'idle';
                        return;
                    }

                    const request = ++this.request;
                    this.status = 'checking';

                    try {
                        const response = await fetch(`${checkUrl}?slug=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
                        const result = await response.json();
                        if (request !== this.request) return; // a newer check is already running

                        this.checked = this.slug;
                        this.status = result.available ? 'available' : 'unavailable';
                        this.message = result.message;
                        this.preview = result.url;
                    } catch {
                        if (request !== this.request) return;
                        this.status = 'unavailable';
                        this.message = 'Could not check right now. Please try again.';
                    }
                },
            }));
        });
    </script>
@endpush
