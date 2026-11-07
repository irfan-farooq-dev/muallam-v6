@extends('admin.layouts.app')

@section('title', 'Categories')

@section('page_actions')
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="bx bx-plus me-1"></i>Add category</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 90px;">Order</th>
                        <th>Category</th>
                        <th class="text-end">Posts</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                    @forelse ($categories as $category)
                    {{-- One table body per category so the delete row can share its open/closed state. --}}
                    <tbody x-data="{ deleting: false }">
                        <tr>
                            <td class="text-nowrap">
                                @foreach (['up' => 'bx-chevron-up', 'down' => 'bx-chevron-down'] as $direction => $icon)
                                    <form method="POST" action="{{ route('admin.categories.move', $category) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="direction" value="{{ $direction }}">
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary"
                                                @disabled(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last))
                                                aria-label="Move {{ $category->name }} {{ $direction }}"><i class="bx {{ $icon }}"></i></button>
                                    </form>
                                @endforeach
                            </td>
                            <td>
                                <div class="fw-semibold">
                                    {{ $category->name }}
                                    @if ($category->slug === $defaultSlug)
                                        <span class="badge bg-label-info ms-1">Default</span>
                                    @endif
                                </div>
                                <div class="small text-muted">
                                    @if ($category->is_active)
                                        <a href="{{ route('categories.show', $category) }}" target="_blank">/category/{{ $category->slug }}</a>
                                    @else
                                        /category/{{ $category->slug }}
                                    @endif
                                    @if ($category->description)
                                        &middot; {{ \Illuminate\Support\Str::limit($category->description, 70) }}
                                    @endif
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <span title="Public posts">{{ $category->public_posts_count }}</span>
                                <span class="text-muted small" title="All posts, including drafts and private">/ {{ $category->posts_count }}</span>
                            </td>
                            <td>
                                <span @class(['badge', 'bg-label-success' => $category->is_active, 'bg-label-secondary' => ! $category->is_active])>
                                    {{ $category->is_active ? 'Active' : 'Hidden' }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-edit-alt me-1"></i>Edit</a>
                                @if ($category->slug !== $defaultSlug)
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="deleting = !deleting" aria-label="Delete {{ $category->name }}"><i class="bx bx-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                        @if ($category->slug !== $defaultSlug)
                            <tr x-show="deleting" x-cloak class="table-danger">
                                <td colspan="5">
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="d-flex flex-wrap align-items-center gap-2"
                                          onsubmit="return confirm(@js('Delete the category "'.$category->name.'"?'))">
                                        @csrf
                                        @method('DELETE')
                                        @if ($category->posts_count > 0)
                                            <span class="small">Move its {{ $category->posts_count }} {{ \Illuminate\Support\Str::plural('post', $category->posts_count) }} to</span>
                                            <select name="move_to" class="form-select form-select-sm w-auto" required>
                                                @foreach ($categories->reject(fn ($other) => $other->is($category)) as $other)
                                                    <option value="{{ $other->id }}" @selected($other->slug === $defaultSlug)>{{ $other->name }}</option>
                                                @endforeach
                                            </select>
                                            <span class="small">and</span>
                                        @else
                                            <span class="small">This category has no posts.</span>
                                        @endif
                                        <button type="submit" class="btn btn-sm btn-danger">Delete category</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                    @empty
                    <tbody><tr><td colspan="5" class="text-center text-muted py-4">No categories yet.</td></tr></tbody>
                    @endforelse
            </table>
        </div>
    </div>
    <p class="small text-muted mt-2 mb-0">The order here is the order of the site's Categories menu. "Hidden" categories disappear from the menu and their pages show "not found"; their posts stay.</p>
@endsection
