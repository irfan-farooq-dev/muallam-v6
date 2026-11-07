@extends('layouts.member')

@section('title', $post->exists ? 'Edit post' : 'New post')

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/quill/quill.snow.css') }}">
@endpush

@section('page_actions')
    <a href="{{ route('posts.mine') }}" class="btn btn-link text-secondary"><i class="bi bi-arrow-left me-1"></i>My posts</a>
@endsection

@section('member_content')
    @php
        $isScheduled = $post->isScheduled();
        $initial = [
            'body' => old('body', $post->body ?? ''),
            'status' => old('status', $post->status?->value ?? 'draft'),
            'publishOption' => old('publish_option', $isScheduled ? 'schedule' : 'now'),
            'scheduledAt' => old('scheduled_at', $isScheduled ? $post->published_at->format('Y-m-d\TH:i') : ''),
            'summary' => old('summary', $post->summary ?? ''),
            'featuredImage' => $post->featuredImageUrl(),
        ];
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger">Please fix the highlighted fields below.</div>
    @endif

    <form method="POST" enctype="multipart/form-data" novalidate
          action="{{ $post->exists ? route('posts.update', $post) : route('posts.store') }}"
          x-data="postEditor(@js($initial))" @submit="beforeSubmit()">
        @csrf
        @if ($post->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="mb-3">
                    <label for="title" class="form-label fw-semibold">Title</label>
                    <input type="text" id="title" name="title" maxlength="200" required
                           value="{{ old('title', $post->title) }}"
                           @class(['form-control form-control-lg', 'is-invalid' => $errors->has('title')])
                           @input="dirty = true">
                    <div class="field-error">{{ $errors->first('title') }}</div>
                </div>

                <div class="mb-3">
                    <label for="summary" class="form-label fw-semibold">Summary <span class="text-secondary fw-normal">(optional, shown on post cards)</span></label>
                    <textarea id="summary" name="summary" rows="2" maxlength="{{ \App\Http\Requests\Posts\SavePostRequest::SUMMARY_MAX }}"
                              @class(['form-control', 'is-invalid' => $errors->has('summary')])
                              x-model="summary" @input="dirty = true"></textarea>
                    <div class="d-flex justify-content-between">
                        <div class="field-error">{{ $errors->first('summary') }}</div>
                        <small class="text-secondary"><span x-text="summary.length"></span> / {{ \App\Http\Requests\Posts\SavePostRequest::SUMMARY_MAX }}</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" id="body-label">Content</label>
                    <div @class(['post-editor', 'is-invalid' => $errors->has('body')])>
                        <div x-ref="editor" aria-labelledby="body-label"></div>
                    </div>
                    <input type="hidden" name="body" x-ref="body">
                    <div class="field-error">{{ $errors->first('body') }}</div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card settings-card mb-3">
                    <div class="card-body">
                        <h3 class="h6 mb-3">Publishing</h3>

                        <label for="status" class="form-label small text-secondary mb-1">Status</label>
                        <select id="status" name="status" class="form-select mb-3" x-model="status" @change="dirty = true">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <p class="small text-secondary mb-3" x-show="status === 'draft'">Only you can see drafts.</p>
                        <p class="small text-secondary mb-3" x-show="status === 'private'" x-cloak>Only you (and site admins) can see private posts.</p>
                        <p class="small text-secondary mb-3" x-show="status === 'restricted'" x-cloak>Only you and admins can see it for now. Sharing it with invited readers arrives in a later update.</p>

                        <div x-show="status === 'published'" x-cloak>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="publish_option" id="publish_now" value="now" x-model="publishOption" @change="dirty = true">
                                <label class="form-check-label" for="publish_now">
                                    {{ $post->exists && $post->status === \App\Enums\PostStatus::Published && ! $isScheduled ? 'Keep it live' : 'Publish now' }}
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="publish_option" id="publish_schedule" value="schedule" x-model="publishOption" @change="dirty = true">
                                <label class="form-check-label" for="publish_schedule">Schedule for later</label>
                            </div>
                            <div x-show="publishOption === 'schedule'" x-cloak>
                                <input type="datetime-local" name="scheduled_at" x-model="scheduledAt" @change="dirty = true"
                                       @class(['form-control form-control-sm', 'is-invalid' => $errors->has('scheduled_at')])
                                       aria-label="Go-live date and time">
                                <small class="text-secondary">Time zone: {{ config('app.timezone') }}</small>
                            </div>
                            <div class="field-error">{{ $errors->first('scheduled_at') }}</div>
                        </div>
                        <div class="field-error">{{ $errors->first('status') }}</div>

                        <label for="category_id" class="form-label small text-secondary mb-1 mt-2">Category</label>
                        <select id="category_id" name="category_id" @class(['form-select', 'is-invalid' => $errors->has('category_id')]) @change="dirty = true">
                            <option value="">Uncategorised</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $post->category_id) === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <div class="field-error">{{ $errors->first('category_id') }}</div>

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="allow_comments" name="allow_comments" value="1"
                                   @checked(old('allow_comments', $post->exists ? $post->allow_comments : true)) @change="dirty = true">
                            <label class="form-check-label" for="allow_comments">Allow comments</label>
                        </div>
                    </div>
                </div>

                <div class="card settings-card mb-3">
                    <div class="card-body">
                        <h3 class="h6 mb-1">Featured image</h3>
                        <p class="small text-secondary">JPG, PNG or WebP, up to 4 MB, at least 400×200.</p>

                        <template x-if="preview && !removeImage">
                            <img :src="preview" alt="Featured image preview" class="img-fluid rounded mb-2 featured-preview">
                        </template>

                        <label class="btn btn-sm btn-outline-accent mb-0">
                            <i class="bi bi-image me-1"></i><span x-text="preview && !removeImage ? 'Replace image' : 'Choose image'"></span>
                            <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp" class="d-none" x-ref="imageInput" @change="chooseImage($event)">
                        </label>
                        <button type="button" class="btn btn-sm btn-link text-danger" x-show="preview && !removeImage" @click="clearImage()">Remove</button>
                        <input type="hidden" name="remove_featured_image" :value="removeImage ? 1 : 0">
                        <div class="field-error">{{ $errors->first('featured_image') }}</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-accent btn-lg w-100" :disabled="submitting">
                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    <span x-text="submitLabel">Save</span>
                </button>
            </div>
        </div>
    </form>

    @if ($post->exists)
        <form method="POST" action="{{ route('posts.destroy', $post) }}" class="mt-4 text-end"
              onsubmit="return confirm('Delete this post? This cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-link text-danger"><i class="bi bi-trash me-1"></i>Delete post</button>
        </form>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('vendor/quill/quill.js') }}"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('postEditor', (initial) => {
                // Kept outside Alpine's reactive state: Alpine wraps reactive objects in proxies, which break Quill.
                let quill = null;

                return {
                status: initial.status,
                publishOption: initial.publishOption,
                scheduledAt: initial.scheduledAt,
                summary: initial.summary,
                preview: initial.featuredImage,
                removeImage: false,
                dirty: false,
                submitting: false,

                init() {
                    quill = new Quill(this.$refs.editor, {
                        theme: 'snow',
                        placeholder: 'Write your post…',
                        modules: {
                            toolbar: [
                                [{ header: [2, 3, false] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                ['blockquote', 'code-block'],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                [{ align: [] }],
                                ['link'],
                                ['clean'],
                            ],
                        },
                    });

                    if (initial.body) {
                        quill.clipboard.dangerouslyPasteHTML(initial.body, 'silent');
                    }
                    quill.on('text-change', () => this.dirty = true);

                    window.addEventListener('beforeunload', (event) => {
                        if (this.dirty && !this.submitting) {
                            event.preventDefault();
                        }
                    });
                },

                get submitLabel() {
                    if (this.status === 'published') return this.publishOption === 'schedule' ? 'Schedule' : 'Publish';
                    return this.status === 'private' ? 'Save privately' : 'Save draft';
                },

                chooseImage(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    this.preview = URL.createObjectURL(file);
                    this.removeImage = false;
                    this.dirty = true;
                },

                clearImage() {
                    this.$refs.imageInput.value = '';
                    this.removeImage = true;
                    this.dirty = true;
                },

                beforeSubmit() {
                    // Quill 2.0.3's getSemanticHTML() turns every space into &nbsp;, which stops lines wrapping. Undo that.
                    this.$refs.body.value = quill.getLength() > 1 ? quill.getSemanticHTML().replaceAll('&nbsp;', ' ') : '';
                    this.submitting = true;
                },

                editor() {
                    return quill;
                },
                };
            });
        });
    </script>
@endpush
