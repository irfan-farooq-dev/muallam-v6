@extends('admin.layouts.app')

@section('title', 'Users')

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end" role="search">
                <div class="col-md-4">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" id="search" name="search" value="{{ $search }}" class="form-control" placeholder="Name, email or slug">
                </div>
                <div class="col-6 col-md-2">
                    <label for="role" class="form-label small">Role</label>
                    <select id="role" name="role" class="form-select">
                        <option value="">Any</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="status" class="form-label small">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Any</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Deactivated</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="verified" class="form-label small">Email</label>
                    <select id="verified" name="verified" class="form-select">
                        <option value="">Any</option>
                        <option value="yes" @selected(($filters['verified'] ?? '') === 'yes')>Verified</option>
                        <option value="no" @selected(($filters['verified'] ?? '') === 'no')>Not verified</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    @if ($search !== '' || array_filter($filters))
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary" title="Clear filters"><i class="bx bx-x"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-end">Posts</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <x-user-avatar :user="$user" :size="36" />
                                    <div>
                                        <a href="{{ route('admin.users.edit', $user) }}" class="fw-semibold text-body">{{ $user->name }}</a>
                                        @if ($user->is(auth()->user()))
                                            <span class="badge bg-label-secondary ms-1">You</span>
                                        @endif
                                        <div class="small text-muted">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span @class(['badge', 'bg-label-primary' => $user->isAdmin(), 'bg-label-secondary' => ! $user->isAdmin()])>{{ $user->role->label() }}</span></td>
                            <td>
                                @if (! $user->is_active)
                                    <span class="badge bg-label-danger">Deactivated</span>
                                @elseif (! $user->hasVerifiedEmail())
                                    <span class="badge bg-label-warning">Not verified</span>
                                @else
                                    <span class="badge bg-label-success">Active</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($user->posts_count > 0)
                                    <a href="{{ route('users.posts', $user->slug) }}" target="_blank" title="Public posts page">{{ $user->posts_count }}</a>
                                @else
                                    0
                                @endif
                            </td>
                            <td><span title="{{ $user->created_at->format('j M Y, g:i a') }}">{{ $user->created_at->format('j M Y') }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-edit-alt me-1"></i>Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No users match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="card-body border-top">{{ $users->links() }}</div>
        @endif
    </div>
    <p class="small text-muted mt-2 mb-0">{{ trans_choice('{1} 1 user|[0,*] :count users', $users->total()) }}</p>
@endsection
