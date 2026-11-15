@extends('layouts.member')

@section('title', $post->exists ? 'Edit post' : 'New post')

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/quill/quill.snow.css') }}">
@endpush

@section('page_actions')
    @if ($post->exists && ! auth()->user()->is($post->user) && Route::has('admin.posts.index'))
        <a href="{{ route('admin.posts.index') }}" class="btn btn-link text-secondary"><i class="bi bi-arrow-left me-1"></i>Admin: posts</a>
    @else
        <a href="{{ route('posts.mine') }}" class="btn btn-link text-secondary"><i class="bi bi-arrow-left me-1"></i>My posts</a>
    @endif
@endsection

@section('member_content')
    @php
        $isScheduled = $post->isScheduled();
        $isLive = $post->exists && $post->status === \App\Enums\PostStatus::Published && ! $isScheduled;
        $isRestricted = $post->status === \App\Enums\PostStatus::Restricted;
        $initial = [
            'body' => old('body', $post->body ?? ''),
            'visibility' => old('visibility', match ($post->status) {
                \App\Enums\PostStatus::Private => 'private',
                \App\Enums\PostStatus::Restricted => 'restricted',
                default => 'public',
            }),
            'schedule' => (bool) old('schedule', $isScheduled),
            // A saved schedule is sent in UTC and shown in the writer's local time by the script; after a failed save, what they typed.
            'scheduledAt' => old('scheduled_at', ''),
            'scheduledAtUtc' => $isScheduled ? $post->published_at->toIso8601String() : null,
            'featuredImage' => $post->featuredImageUrl(),
            'isLive' => $isLive,
            'imageUploadUrl' => route('posts.images.store'),
            'titleMin' => \App\Http\Requests\Posts\SavePostRequest::TITLE_MIN,
            'bodyMin' => \App\Http\Requests\Posts\SavePostRequest::BODY_MIN_CHARACTERS,
            // Reopen on the step that has the problem: title or content errors on "Write", any other error on the options.
            'step' => match (true) {
                $errors->hasAny(['title', 'body']) => 1,
                $errors->any() || $errors->sharing->any() => 2,
                default => 1,
            },
        ];
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger">
            Please fix the highlighted fields below.
            {{-- Errors for inputs that have no visible field of their own (e.g. the clicked button). --}}
            @foreach (['action', 'status', 'publish_option', 'allow_comments', 'remove_featured_image'] as $hiddenField)
                @error($hiddenField)
                    <div class="small mt-1">{{ $message }}</div>
                @enderror
            @endforeach
        </div>
    @endif

    @if ($pendingAdminEdit && auth()->user()->can('reviewAdminEdits', $post))
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2" data-admin-edit-notice>
            <span>
                <i class="bi bi-person-gear me-1"></i>
                <strong>{{ $pendingAdminEdit->editor?->name ?? 'An admin' }}</strong> edited this post {{ $pendingAdminEdit->updated_at->diffForHumans() }}.
                Saving it yourself marks the changes as reviewed.
            </span>
            <a href="{{ route('posts.changes', $post) }}" class="btn btn-sm btn-accent">See what changed</a>
        </div>
    @elseif ($post->exists && ! auth()->user()->is($post->user))
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" data-admin-edit-notice>
            <span>
                <i class="bi bi-shield-exclamation me-1"></i>
                You are editing <strong>{{ $post->user->name }}</strong>'s post as an admin.
                Changes to the title, summary and text are highlighted for the author (readers only see the plain post).
            </span>
            @if ($pendingAdminEdit)
                <a href="{{ route('posts.changes', $post) }}" class="btn btn-sm btn-outline-secondary">Changes so far</a>
            @endif
        </div>
    @endif

    {{-- Two steps in one form: 1 = write (title and content), 2 = publishing options. Nothing is saved between the steps. --}}
    <div x-data="postEditor(@js($initial))" data-start-step="{{ $initial['step'] }}">
    <ol class="editor-steps list-unstyled d-flex flex-wrap gap-2 mb-4" aria-label="Steps">
        <li>
            <button type="button" class="editor-step" :class="step === 1 && 'active'" :aria-current="step === 1 ? 'step' : false" @click="step = 1">
                <span class="editor-step-number">1</span>Write
            </button>
        </li>
        <li class="editor-step-divider" aria-hidden="true"></li>
        <li>
            <button type="button" class="editor-step" :class="step === 2 && 'active'" :aria-current="step === 2 ? 'step' : false" @click="goToOptions()">
                <span class="editor-step-number">2</span>Publishing options
            </button>
        </li>
    </ol>

    <form method="POST" enctype="multipart/form-data" novalidate
          action="{{ $post->exists ? route('posts.update', $post) : route('posts.store') }}"
          @submit="beforeSubmit($event)">
        @csrf
        @if ($post->exists)
            @method('PUT')
            <input type="hidden" name="preview_post" value="{{ $post->slug }}">
        @endif
        {{-- Filled from the clicked button. Disabled buttons are not sent by browsers, and the buttons are disabled while saving. --}}
        <input type="hidden" name="action" x-ref="action" value="publish">
        {{-- Title, summary and content are sent base64-encoded in here, so the host's web firewall does not block normal post text. --}}
        <input type="hidden" name="encoded_fields" x-ref="encoded">
        {{-- The writer's time zone, so a scheduled time means their local time. --}}
        <input type="hidden" name="timezone" :value="timezone">

        {{-- Step 1: write --}}
        <div x-show="step === 1" x-cloak data-step="1">
            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">Title</label>
                <input type="text" id="title" name="title" maxlength="{{ \App\Http\Requests\Posts\SavePostRequest::TITLE_MAX }}" required
                       value="{{ old('title', $post->title) }}" x-ref="title"
                       @class(['form-control form-control-lg', 'is-invalid' => $errors->has('title')])
                       @input="dirty = true; stepError = ''; titleLength = $event.target.value.trim().length">
                <div class="d-flex justify-content-between gap-2">
                    <div class="field-error">{{ $errors->first('title') }}</div>
                    <small class="option-help text-nowrap" x-cloak x-show="titleLength < initial.titleMin">
                        <span x-text="titleLength"></span> / at least <span x-text="initial.titleMin"></span> characters
                    </small>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold" id="body-label">Content</label>
                <div @class(['post-editor', 'is-invalid' => $errors->has('body')]) x-ref="editorWrap">
                    <div x-ref="editor" aria-labelledby="body-label"></div>

                    {{-- Medium-style helpers, so formatting is at hand anywhere in a long post (the toolbar also stays visible while scrolling).
                         mousedown.prevent keeps the text selected while a button is clicked. --}}
                    <div class="editor-bubble" x-ref="bubble" x-show="bubble.show" x-cloak role="toolbar" aria-label="Format selected text"
                         :style="`top: ${bubble.top}px; left: ${bubble.left}px`" @mousedown.prevent>
                        <button type="button" :class="formats.bold && 'active'" @click="formatSelection('bold')" title="Bold (Ctrl+B)" aria-label="Bold"><i class="bi bi-type-bold"></i></button>
                        <button type="button" :class="formats.italic && 'active'" @click="formatSelection('italic')" title="Italic (Ctrl+I)" aria-label="Italic"><i class="bi bi-type-italic"></i></button>
                        <button type="button" :class="formats.underline && 'active'" @click="formatSelection('underline')" title="Underline (Ctrl+U)" aria-label="Underline"><i class="bi bi-type-underline"></i></button>
                        <button type="button" :class="formats.link && 'active'" @click="formatSelection('link')" title="Link" aria-label="Link"><i class="bi bi-link-45deg"></i></button>
                        <span class="editor-bubble-separator" aria-hidden="true"></span>
                        <button type="button" :class="formats.header === 2 && 'active'" @click="formatSelection('header', 2)" title="Heading" aria-label="Heading"><i class="bi bi-type-h2"></i></button>
                        <button type="button" :class="formats.header === 3 && 'active'" @click="formatSelection('header', 3)" title="Small heading" aria-label="Small heading"><i class="bi bi-type-h3"></i></button>
                        <button type="button" :class="formats.blockquote && 'active'" @click="formatSelection('blockquote')" title="Quote" aria-label="Quote"><i class="bi bi-quote"></i></button>
                        <button type="button" :class="formats.code && 'active'" @click="formatSelection('code')" title="Code" aria-label="Code"><i class="bi bi-code"></i></button>
                    </div>

                    <div class="editor-plus" x-show="plus.show" x-cloak :style="`top: ${plus.top}px`" @mousedown.prevent @keydown.escape="plus.open = false">
                        <button type="button" class="editor-plus-toggle" :class="plus.open && 'open'" @click="plus.open = !plus.open"
                                :aria-expanded="plus.open" title="Insert" aria-label="Insert image, heading, list, quote or code"><i class="bi bi-plus-lg"></i></button>
                        <div class="editor-plus-menu" x-show="plus.open" role="menu">
                            <button type="button" role="menuitem" @click="insertImageHere()" title="Image"><i class="bi bi-image"></i></button>
                            <button type="button" role="menuitem" @click="formatEmptyLine('header', 2)" title="Heading"><i class="bi bi-type-h2"></i></button>
                            <button type="button" role="menuitem" @click="formatEmptyLine('list', 'bullet')" title="Bulleted list"><i class="bi bi-list-ul"></i></button>
                            <button type="button" role="menuitem" @click="formatEmptyLine('list', 'ordered')" title="Numbered list"><i class="bi bi-list-ol"></i></button>
                            <button type="button" role="menuitem" @click="formatEmptyLine('blockquote', true)" title="Quote"><i class="bi bi-quote"></i></button>
                            <button type="button" role="menuitem" @click="formatEmptyLine('code-block', true)" title="Code block"><i class="bi bi-code-slash"></i></button>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="body" x-ref="body">
                <input type="file" accept="image/jpeg,image/png,image/webp" class="d-none" x-ref="inlineImage" @change="chooseInlineImage($event)">
                {{-- Floating, so it is visible wherever the writer has scrolled to. Large photos can take a few seconds on the live host. --}}
                <div class="upload-indicator shadow" x-show="uploading > 0" x-cloak role="status" aria-live="polite">
                    <div class="d-flex align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        <span x-text="uploadPercent < 100 ? 'Uploading image… ' + uploadPercent + '%' : 'Processing image…'">Uploading image…</span>
                    </div>
                    <div class="progress mt-2" role="presentation">
                        <div class="progress-bar" :style="`width: ${uploadPercent}%`"></div>
                    </div>
                </div>
                <div class="field-error" x-show="imageError" x-text="imageError" x-cloak></div>
                <div class="d-flex justify-content-between gap-2">
                    <div class="field-error">{{ $errors->first('body') }}</div>
                    <small class="option-help text-nowrap" x-cloak x-show="bodyLength < initial.bodyMin">
                        <span x-text="bodyLength"></span> / at least <span x-text="initial.bodyMin"></span> characters
                    </small>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-end gap-3">
                <span class="field-error mt-0" x-show="stepError" x-text="stepError" x-cloak></span>
                <button type="button" class="btn btn-accent btn-lg" @click="goToOptions()" :disabled="uploading > 0">
                    Next: publishing options<i class="bi bi-arrow-right ms-2"></i>
                </button>
            </div>
        </div>

        {{-- Step 2: publishing options. Short labels; the "?" beside each one explains it. --}}
        <div x-show="step === 2" x-cloak data-step="2">
            {{-- The summary field was dropped from the editor; an existing summary is kept as it is. --}}
            <input type="hidden" name="summary" value="{{ old('summary', $post->summary) }}">

            <div class="card settings-card">
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="h6 mb-0">Who can read it?</h3>
                                @if ($post->exists)
                                    <x-post-status-badge :post="$post" />
                                @endif
                            </div>
                            <p class="option-help mb-2">You can change this at any time, also after publishing.</p>

                            <div class="form-check editor-option">
                                <input class="form-check-input" type="radio" name="visibility" id="visibility_public" value="public" x-model="visibility" @change="dirty = true">
                                <label class="form-check-label" for="visibility_public">
                                    Everyone
                                </label>
                            </div>
                            <div class="form-check editor-option">
                                <input class="form-check-input" type="radio" name="visibility" id="visibility_private" value="private" x-model="visibility" @change="dirty = true">
                                <label class="form-check-label" for="visibility_private">Only me</label>
                                <x-help-tip text="Nobody else can open it. Good for personal notes." />
                            </div>
                            <div class="form-check editor-option">
                                <input class="form-check-input" type="radio" name="visibility" id="visibility_restricted" value="restricted" x-model="visibility" @change="dirty = true">
                                <label class="form-check-label" for="visibility_restricted">Invited people only</label>
                                <x-help-tip text="Only people you invite by email can read it, with a password you choose. Want feedback before everyone can read it? Use this, invite a friend or editor, then switch to Everyone when it is ready." />
                            </div>

                            <div class="mt-2" x-show="visibility === 'restricted'" x-cloak>
                                <label for="access_password" class="form-label small text-secondary mb-1">
                                    Post password {{ $post->access_password ? '(leave empty to keep the current one)' : '' }}
                                    <x-help-tip text="Invited readers need this. Tell them yourself; it is never put in the invite email." />
                                </label>
                                <input type="text" id="access_password" name="access_password" autocomplete="off" maxlength="72"
                                       @class(['form-control form-control-sm', 'is-invalid' => $errors->has('access_password')])
                                       placeholder="{{ $post->access_password ? 'Unchanged' : 'At least '.\App\Http\Requests\Posts\SavePostRequest::ACCESS_PASSWORD_MIN.' characters' }}"
                                       @input="dirty = true">
                                <div class="field-error">{{ $errors->first('access_password') }}</div>
                            </div>
                            <div class="field-error">{{ $errors->first('visibility') ?: $errors->first('status') }}</div>

                            <div class="mt-3" x-show="visibility === 'public'">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="schedule" name="schedule" value="1" x-model="schedule" @change="dirty = true">
                                    <label class="form-check-label" for="schedule">
                                        Schedule for later
                                    </label>
                                </div>
                                <div x-show="schedule" x-cloak class="mt-2">
                                    <input type="datetime-local" name="scheduled_at" x-model="scheduledAt" @change="dirty = true"
                                           @class(['form-control form-control-sm', 'is-invalid' => $errors->has('scheduled_at')])
                                           aria-label="Go-live date and time">
                                </div>
                                <div class="field-error">{{ $errors->first('scheduled_at') }}</div>
                            </div>

                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="allow_comments" name="allow_comments" value="1"
                                       @checked(old('allow_comments', $post->exists ? $post->allow_comments : true)) @change="dirty = true">
                                <label class="form-check-label" for="allow_comments">Allow comments</label>
                                <x-help-tip text="Signed-in members can comment under your post. You can hide any comment later." />
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <label for="category_id" class="h6 d-block mb-2">Category</label>
                            {{-- <p class="option-help mb-2">Choose the category that best fits your post, so readers browsing that topic find it. If none fits, keep General.</p> --}}
                            <select id="category_id" name="category_id" required @class(['form-select', 'is-invalid' => $errors->has('category_id')]) @change="dirty = true">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id', $post->category_id ?? $defaultCategoryId) === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <div class="field-error">{{ $errors->first('category_id') }}</div>

                            <h3 class="h6 mt-4 mb-0">Featured image <span class="small text-secondary fw-normal">(optional)</span></h3>
                            <p class="option-help mb-2">The main picture of your post: shown at the top of the post and when the link is shared on social media. JPG, PNG or WebP, up to 4 MB, at least 400×200.</p>
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
                </div>
            </div>

            <div class="editor-actions mt-4">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-link text-secondary px-0 me-auto" @click="step = 1; window.scrollTo({ top: 0 })">
                        <i class="bi bi-arrow-left me-1"></i>Back to writing
                    </button>
                    <button type="submit" value="preview" class="btn btn-outline-secondary"
                            formaction="{{ route('posts.preview') }}" formtarget="_blank" formnovalidate>
                        <i class="bi bi-eye me-1"></i>Preview
                    </button>
                    <button type="submit" value="draft" class="btn btn-outline-accent" :disabled="submitting || uploading > 0">
                        <span x-show="submitting === 'draft'" x-cloak class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        <span x-text="isLive ? 'Unpublish (back to draft)' : 'Save draft'">Save draft</span>
                    </button>
                    <button type="submit" value="publish" class="btn btn-accent px-4" :disabled="submitting || uploading > 0">
                        <span x-show="submitting === 'publish'" x-cloak class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        <span x-text="publishLabel">Publish</span>
                    </button>
                </div>
                <p class="small text-secondary mt-2 mb-0 text-end">
                    <span x-text="publishHint"></span>
                    <span x-show="!isLive" x-cloak>Drafts are only visible to you.</span>
                </p>
            </div>
        </div>
    </form>

    <div x-show="step === 2" x-cloak>
        @if ($post->exists && $post->status === \App\Enums\PostStatus::Restricted)
            @include('member.posts.partials.sharing', ['post' => $post, 'shares' => $shares])
        @endif

        @if ($post->exists)
            <form method="POST" action="{{ route('posts.destroy', $post) }}" class="mt-4 text-end"
                  onsubmit="return confirm('Delete this post? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link text-danger"><i class="bi bi-trash me-1"></i>Delete post</button>
            </form>
        @endif
    </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/quill/quill.js') }}"></script>
    <script>
        // "2026-11-14T01:12" in the browser's time zone, the format a datetime-local input expects.
        function toLocalInputValue(date) {
            const pad = (number) => String(number).padStart(2, '0');

            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('postEditor', (initial) => {
                // Kept outside Alpine's reactive state: Alpine wraps reactive objects in proxies, which break Quill.
                let quill = null;

                return {
                // Sending invites returns to #sharing, which lives on the options step.
                step: window.location.hash === '#sharing' ? 2 : initial.step,
                stepError: '',
                visibility: initial.visibility,
                schedule: initial.schedule,
                scheduledAt: initial.scheduledAt || (initial.scheduledAtUtc ? toLocalInputValue(new Date(initial.scheduledAtUtc)) : ''),
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
                isLive: initial.isLive,
                uploading: 0,
                initial,
                titleLength: 0,
                bodyLength: 0,
                bubble: { show: false, top: 0, left: 0 },
                plus: { show: false, open: false, top: 0, index: 0 },
                formats: {},
                // Where a picture chosen from the "+" menu goes (null = at the cursor).
                imageIndex: null,
                uploadPercent: 0,
                imageError: '',
                preview: initial.featuredImage,
                removeImage: false,
                dirty: false,
                submitting: false,

                init() {
                    quill = new Quill(this.$refs.editor, {
                        theme: 'snow',
                        placeholder: 'Write your post…',
                        // Only what the toolbar offers. Text pasted from other sites keeps its words but drops colours, fonts and sizes.
                        formats: ['header', 'bold', 'italic', 'underline', 'strike', 'blockquote', 'code-block', 'code', 'list', 'indent', 'align', 'link', 'image', 'alt', 'width', 'height'],
                        modules: {
                            toolbar: [
                                [{ header: [2, 3, false] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                ['blockquote', 'code-block'],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                [{ align: [] }],
                                ['link', 'image'],
                                ['clean'],
                            ],
                            // Pasted or dropped image files are uploaded instead of being embedded as huge base64 text.
                            uploader: {
                                mimetypes: ['image/jpeg', 'image/png', 'image/webp'],
                                handler: (range, files) => files.forEach((file, offset) => this.uploadImage(file, range.index + offset, this.$refs.title.value.trim())),
                            },
                        },
                    });

                    quill.getModule('toolbar').addHandler('image', () => this.$refs.inlineImage.click());

                    // The toolbar sticks just below the site header while a long post is scrolled.
                    document.documentElement.style.setProperty('--editor-sticky-top', `${document.getElementById('header')?.offsetHeight ?? 0}px`);
                    quill.on('editor-change', () => this.updateFloatingTools());
                    window.addEventListener('resize', () => this.updateFloatingTools());

                    if (initial.body) {
                        quill.clipboard.dangerouslyPasteHTML(initial.body, 'silent');
                    }
                    this.titleLength = this.$refs.title.value.trim().length;
                    this.bodyLength = this.textLength();
                    quill.on('text-change', () => {
                        this.dirty = true;
                        this.stepError = '';
                        this.bodyLength = this.textLength();
                    });

                    window.addEventListener('beforeunload', (event) => {
                        if (this.dirty && !this.submitting) {
                            event.preventDefault();
                        }
                    });
                },

                async uploadImage(file, index, alt = '') {
                    this.uploading++;
                    const form = new FormData();
                    form.append('image', file);
                    if (alt) form.append('alt', alt);

                    try {
                        const response = await this.sendImage(form);
                        const result = response.result;

                        if (!response.ok) {
                            const errors = result.errors ? Object.values(result.errors).flat() : [result.message];
                            this.imageError = errors[0] || 'The image could not be uploaded.';
                            return;
                        }

                        quill.insertEmbed(index, 'image', result.url, 'user');
                        quill.formatText(index, 1, { alt: result.alt || '', width: String(result.width), height: String(result.height) }, 'user');
                        quill.setSelection(index + 1, 0, 'silent');
                        this.imageError = '';
                    } catch {
                        this.imageError = 'The image could not be uploaded. Check your connection and try again.';
                    } finally {
                        this.uploading--;
                    }
                },

                // XMLHttpRequest instead of fetch() because only it reports upload progress, shown in the floating indicator.
                sendImage(form) {
                    this.uploadPercent = 0;

                    return new Promise((resolve, reject) => {
                        const request = new XMLHttpRequest();
                        request.open('POST', initial.imageUploadUrl);
                        request.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=csrf-token]').content);
                        request.setRequestHeader('Accept', 'application/json');
                        request.upload.onprogress = (event) => {
                            if (event.lengthComputable) {
                                this.uploadPercent = Math.round((event.loaded / event.total) * 100);
                            }
                        };
                        request.onload = () => {
                            let result = {};
                            try {
                                result = JSON.parse(request.responseText);
                            } catch {
                                result = { message: 'The image could not be uploaded.' };
                            }
                            resolve({ ok: request.status >= 200 && request.status < 300, result });
                        };
                        request.onerror = () => reject(new Error('Network error'));
                        request.send(form);
                    });
                },

                chooseInlineImage(event) {
                    const file = event.target.files[0];
                    event.target.value = '';
                    const chosenIndex = this.imageIndex;
                    this.imageIndex = null;
                    if (!file) return;

                    // The post title describes the image for screen readers and search engines, so writers are not asked.
                    const range = quill.getSelection(true);
                    const index = chosenIndex ?? (range ? range.index : quill.getLength());
                    this.uploadImage(file, index, this.$refs.title.value.trim());
                },

                /*
                 * Selected text: a small format bar above it. Cursor on an empty line: a "+" at the start of the line
                 * (image, heading, lists, quote, code). Positions are relative to the editor box (.post-editor).
                 */
                async updateFloatingTools() {
                    const range = quill.getSelection();
                    const container = quill.container;

                    if (!range) {
                        this.bubble.show = false;
                        this.plus.show = false;
                        this.plus.open = false;
                        return;
                    }

                    if (range.length > 0) {
                        this.plus.show = false;
                        this.plus.open = false;
                        this.formats = quill.getFormat(range);
                        this.bubble.show = true;
                        await this.$nextTick();

                        const bounds = quill.getBounds(range.index, range.length);
                        const bubble = this.$refs.bubble;
                        const maxLeft = this.$refs.editorWrap.clientWidth - bubble.offsetWidth;
                        const above = container.offsetTop + bounds.top - bubble.offsetHeight - 8;

                        this.bubble.left = Math.max(0, Math.min(maxLeft, container.offsetLeft + bounds.left + bounds.width / 2 - bubble.offsetWidth / 2));
                        this.bubble.top = above >= container.offsetTop ? above : container.offsetTop + bounds.bottom + 8;
                        return;
                    }

                    this.bubble.show = false;
                    const [line] = quill.getLine(range.index);
                    const isEmptyParagraph = line && line.length() === 1 && line.domNode.tagName === 'P';

                    if (!isEmptyParagraph) {
                        this.plus.show = false;
                        this.plus.open = false;
                        return;
                    }

                    const bounds = quill.getBounds(range.index);
                    this.plus.index = range.index;
                    this.plus.top = container.offsetTop + bounds.top + bounds.height / 2 - 15;
                    this.plus.show = true;
                },

                formatSelection(name, value = true) {
                    const range = quill.getSelection();
                    if (!range) return;

                    if (name === 'link') {
                        if (this.formats.link) {
                            quill.format('link', false, 'user');
                        } else {
                            // Quill's own link box (the same one the toolbar uses).
                            const toolbar = quill.getModule('toolbar');
                            toolbar.handlers.link.call(toolbar, true);
                            this.bubble.show = false;
                            return;
                        }
                    } else if (name === 'header') {
                        quill.format('header', this.formats.header === value ? false : value, 'user');
                    } else {
                        quill.format(name, !this.formats[name], 'user');
                    }

                    this.formats = quill.getFormat(quill.getSelection() ?? range);
                },

                insertImageHere() {
                    this.plus.open = false;
                    this.imageIndex = this.plus.index;
                    this.$refs.inlineImage.click();
                },

                formatEmptyLine(name, value) {
                    quill.formatLine(this.plus.index, 1, name, value, 'user');
                    quill.setSelection(this.plus.index, 0, 'user');
                    this.plus.open = false;
                },

                get publishLabel() {
                    if (this.visibility === 'private') return 'Save as private';
                    if (this.visibility === 'restricted') return 'Save as restricted';
                    if (this.schedule) return 'Schedule';
                    return this.isLive ? 'Update' : 'Publish';
                },

                get publishHint() {
                    if (this.visibility === 'private') return 'Private posts are only visible to you.';
                    if (this.visibility === 'restricted') return 'Only people you invite below (with the password) can read it.';
                    if (this.schedule) return 'The post goes live automatically at the chosen time.';
                    return this.isLive ? 'Saves your changes to the live post.' : 'Publishing makes the post visible to everyone right away.';
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

                // Readable characters, counted like the server does (PostBodySanitizer::textLength): runs of spaces and line breaks count as one.
                textLength() {
                    return quill.getText().replace(/\s+/g, ' ').trim().length;
                },

                // The server checks everything again; this only stops a too-short post from reaching step 2.
                goToOptions() {
                    if (this.titleLength < initial.titleMin) {
                        this.step = 1;
                        this.stepError = this.titleLength === 0 ? 'Please add a title first.' : `The title must be at least ${initial.titleMin} characters long.`;
                        this.$refs.title.focus();
                        return;
                    }

                    if (this.bodyLength < initial.bodyMin) {
                        this.step = 1;
                        this.stepError = this.bodyLength === 0
                            ? 'Please write some content first.'
                            : `The content must be at least ${initial.bodyMin} characters long (it has ${this.bodyLength}).`;
                        quill.focus();
                        return;
                    }

                    this.stepError = '';
                    this.step = 2;
                    window.scrollTo({ top: 0 });
                },

                beforeSubmit(event) {
                    // Quill 2.0.3's getSemanticHTML() turns every space into &nbsp;, which stops lines wrapping. Undo that.
                    this.$refs.body.value = quill.getLength() > 1 ? quill.getSemanticHTML().replaceAll('&nbsp;', ' ') : '';

                    const action = event.submitter?.value ?? 'publish';
                    this.$refs.action.value = action;

                    this.encodeTextFields(event.target);

                    // Preview opens in a new tab and saves nothing, so the editor stays usable.
                    if (action === 'preview') {
                        return;
                    }

                    this.submitting = action;
                },

                // Sends title, summary and content as one base64 field (decoded by the DecodeEncodedFormFields
                // middleware) and leaves the plain fields out of this submit, so a web firewall never sees the HTML.
                // The browser builds the form data right after this handler, so the fields can be re-enabled straight away.
                encodeTextFields(form) {
                    const plainFields = [form.elements.title, form.elements.summary, this.$refs.body];
                    const json = JSON.stringify({ title: plainFields[0].value, summary: plainFields[1].value, body: plainFields[2].value });
                    const bytes = new TextEncoder().encode(json);
                    let binary = '';

                    for (let i = 0; i < bytes.length; i += 0x8000) {
                        binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
                    }

                    this.$refs.encoded.value = btoa(binary);
                    plainFields.forEach((field) => field.disabled = true);
                    setTimeout(() => plainFields.forEach((field) => field.disabled = false));
                },

                editor() {
                    return quill;
                },
                };
            });
        });
    </script>
@endpush
