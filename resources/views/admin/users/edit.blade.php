@extends('admin.layouts.app')

@section('title', 'Edit user')

@section('page_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>All users</a>
@endsection

@section('content')
    @php($isSelf = $user->is(auth()->user()))

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full name</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="100" required
                                       @class(['form-control', 'is-invalid' => $errors->has('name')])>
                                <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" required
                                       @class(['form-control', 'is-invalid' => $errors->has('email')])>
                                <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Role</label>
                                <select id="role" name="role" @class(['form-select', 'is-invalid' => $errors->has('role')])>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">{{ $errors->first('role') }}</div>
                                <div class="form-text">Admins can open this panel and edit or delete any post.</div>
                            </div>
                            <div class="col-md-6">
                                <span class="form-label d-block">Account</span>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input @if ($errors->has('is_active')) is-invalid @endif" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                           @checked(old('is_active', $user->is_active))>
                                    <label class="form-check-label" for="is_active">Active (can sign in)</label>
                                    <div class="invalid-feedback">{{ $errors->first('is_active') }}</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="email_verified" name="email_verified" value="1"
                                           @checked(old('email_verified', $user->hasVerifiedEmail()))>
                                    <label class="form-check-label" for="email_verified">Email verified</label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning small mt-3 mb-0">
                            Turning <strong>Active</strong> off signs this person out on every device straight away, and they cannot sign in again until it is turned back on.
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">Save changes</button>
                    </form>
                </div>
            </div>

            @unless ($isSelf)
                <div class="card mt-4 border border-danger" x-data="{ open: {{ $errors->has('confirm_email') ? 'true' : 'false' }} }">
                    <div class="card-body">
                        <h5 class="text-danger mb-1">Delete this user</h5>
                        <p class="small mb-2">
                            Permanently deletes {{ $user->name }} with all {{ $user->posts_count }} posts (and their images),
                            {{ $user->comments_count }} comments and {{ $user->likes_count }} likes. This cannot be undone.
                            To only stop them signing in, turn <strong>Active</strong> off instead.
                        </p>
                        <button type="button" class="btn btn-outline-danger btn-sm" x-show="!open" @click="open = true">Delete user…</button>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" x-show="open" x-cloak class="mt-2">
                            @csrf
                            @method('DELETE')
                            <label for="confirm_email" class="form-label small">Type <strong>{{ $user->email }}</strong> to confirm</label>
                            <div class="d-flex gap-2">
                                <input type="text" id="confirm_email" name="confirm_email" autocomplete="off"
                                       @class(['form-control form-control-sm', 'is-invalid' => $errors->has('confirm_email')])>
                                <button type="submit" class="btn btn-danger btn-sm text-nowrap">Delete permanently</button>
                            </div>
                            <div class="text-danger small mt-1">{{ $errors->first('confirm_email') }}</div>
                        </form>
                    </div>
                </div>
            @endunless
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center">
                    <x-user-avatar :user="$user" :size="80" class="mb-2" />
                    <h5 class="mb-0">{{ $user->name }}</h5>
                    <div class="small text-muted mb-3">{{ $user->email }}</div>
                    <ul class="list-unstyled text-start small mb-3">
                        <li class="d-flex justify-content-between py-1"><span>Joined</span><strong><x-local-time :at="$user->created_at" format="date" /></strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Posts</span><strong>{{ $user->posts_count }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Comments</span><strong>{{ $user->comments_count }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Likes given</span><strong>{{ $user->likes_count }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Profile link</span><strong>/users/{{ $user->slug }}</strong></li>
                    </ul>
                    <a href="{{ route('users.posts', $user->slug) }}" target="_blank" class="btn btn-sm btn-outline-primary">View public page</a>
                </div>
            </div>
        </div>
    </div>
@endsection
