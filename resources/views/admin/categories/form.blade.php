@extends('admin.layouts.app')

@section('title', $category->exists ? 'Edit category' : 'Add category')

@section('page_actions')
    <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>All categories</a>
@endsection

@section('content')
    @php($isDefault = $category->exists && $category->slug === config('muallam.default_category_slug'))

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" novalidate
                          action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
                          x-data="{ name: @js(old('name', $category->name ?? '')), slug: @js(old('slug', $category->slug ?? '')), slugTouched: @js($category->exists),
                                    toSlug(text) { return text.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/&/g, ' ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); } }">
                        @csrf
                        @if ($category->exists)
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" id="name" name="name" maxlength="100" required x-model="name"
                                   @input="if (!slugTouched) slug = toSlug(name)"
                                   @class(['form-control', 'is-invalid' => $errors->has('name')])>
                            <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Link</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ url('category') }}/</span>
                                <input type="text" id="slug" name="slug" maxlength="120" x-model="slug" @input="slugTouched = true"
                                       @readonly($isDefault)
                                       @class(['form-control', 'is-invalid' => $errors->has('slug')])>
                            </div>
                            <div class="text-danger small mt-1">{{ $errors->first('slug') }}</div>
                            @if ($category->exists && ! $isDefault)
                                <div class="form-text">Changing the link breaks old links and search results for this category.</div>
                            @elseif ($isDefault)
                                <div class="form-text">The default category's link cannot change.</div>
                            @else
                                <div class="form-text">Filled in from the name. Lowercase letters, numbers and hyphens.</div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-muted">(optional)</span></label>
                            <textarea id="description" name="description" rows="2" maxlength="300"
                                      @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $category->description) }}</textarea>
                            <div class="invalid-feedback">{{ $errors->first('description') }}</div>
                            <div class="form-text">Shown under the category's name on its page and in search results.</div>
                        </div>

                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input @if ($errors->has('is_active')) is-invalid @endif" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                   @checked(old('is_active', $category->is_active ?? true)) @disabled($isDefault)>
                            <label class="form-check-label" for="is_active">Active (shown in the menu, and writers can choose it)</label>
                        </div>
                        @if ($isDefault)
                            <input type="hidden" name="is_active" value="1">
                            <div class="form-text">The default category must stay active; new posts start in it.</div>
                        @endif
                        <div class="text-danger small">{{ $errors->first('is_active') }}</div>

                        <button type="submit" class="btn btn-primary mt-3">{{ $category->exists ? 'Save changes' : 'Add category' }}</button>
                    </form>
                </div>
            </div>
        </div>

        @if ($category->exists)
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body small">
                        <div class="d-flex justify-content-between py-1"><span>Posts</span><strong>{{ $category->posts_count }}</strong></div>
                        <div class="d-flex justify-content-between py-1"><span>Created</span><strong>{{ $category->created_at->format('j M Y') }}</strong></div>
                        @if ($category->is_active)
                            <a href="{{ route('categories.show', $category) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">View on site</a>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
