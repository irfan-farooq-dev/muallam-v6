@extends('layouts.app')

@section('title', 'Contact us')
@section('meta_description', 'Send feedback, suggestions, complaints or questions to the Muallam team.')

@section('content')
    <section class="section">
        <div class="container">
            <x-breadcrumbs class="mb-2" :items="[['Contact us']]" />
            <h2 class="h3 mb-1">Contact us</h2>
            <p class="text-secondary mb-4">Feedback, a suggestion, a problem with the site or a business question? Send us a message.</p>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card contact-card">
                        <div class="card-body p-4">
                            <form method="POST" action="{{ route('contact.store') }}" novalidate>
                                @csrf

                                {{-- Hidden from people; bots that fill it in are ignored. --}}
                                <div class="contact-trap" aria-hidden="true">
                                    <label for="website">Leave this empty</label>
                                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" id="name" name="name" placeholder="Name" maxlength="100" required autocomplete="name"
                                                   value="{{ old('name', auth()->user()?->name) }}" @class(['form-control', 'is-invalid' => $errors->has('name')])>
                                            <label for="name">Name</label>
                                        </div>
                                        @error('name')<div class="field-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" id="email" name="email" placeholder="Email" maxlength="255" required autocomplete="email"
                                                   value="{{ old('email', auth()->user()?->email) }}" @class(['form-control', 'is-invalid' => $errors->has('email')])>
                                            <label for="email">Email</label>
                                        </div>
                                        @error('email')<div class="field-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <select id="subject" name="subject" required @class(['form-select', 'is-invalid' => $errors->has('subject')])>
                                                <option value="" disabled @selected(! old('subject'))>Choose a subject</option>
                                                @foreach ($subjects as $subject)
                                                    <option value="{{ $subject->value }}" @selected(old('subject') === $subject->value)>{{ $subject->label() }}</option>
                                                @endforeach
                                            </select>
                                            <label for="subject">Subject</label>
                                        </div>
                                        @error('subject')<div class="field-error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12" x-data="{ message: @js(old('message', '')) }">
                                        <div class="form-floating">
                                            <textarea id="message" name="message" placeholder="Message" style="height: 180px" maxlength="5000" required
                                                      x-model="message" @class(['form-control', 'is-invalid' => $errors->has('message')])>{{ old('message') }}</textarea>
                                            <label for="message">Your message</label>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <div>@error('message')<div class="field-error">{{ $message }}</div>@enderror</div>
                                            <small class="text-secondary" x-text="`${message.length} / 5000`"></small>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-accent mt-3"><i class="bi bi-send me-1"></i>Send message</button>
                                <p class="small text-secondary mt-3 mb-0">
                                    We use your name and email only to reply. See our <a href="{{ route('privacy') }}">privacy policy</a>.
                                </p>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="sidebar-box">
                        <h3 class="sidebar-title">Other ways to reach us</h3>
                        <p class="small mb-2"><i class="bi bi-envelope me-2 text-secondary"></i><a href="mailto:{{ config('muallam.contact_email') }}">{{ config('muallam.contact_email') }}</a></p>
                        <p class="small text-secondary mb-3">We usually reply within two working days.</p>
                        <h3 class="sidebar-title mt-4">Before you write</h3>
                        <ul class="small text-secondary ps-3 mb-0">
                            <li>Forgot your password? Use <a href="{{ route('password.request') }}">reset password</a>.</li>
                            <li>To report a post or comment, include its link.</li>
                            <li>Read our <a href="{{ route('terms') }}">terms &amp; conditions</a>.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
