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
                <input type="text" id="title" name="title" maxlength="200" required
                       value="{{ old('title', $post->title) }}" x-ref="title"
                       @class(['form-control form-control-lg', 'is-invalid' => $errors->has('title')])
                       @input="dirty = true; stepError = ''">
                <div class="field-error">{{ $errors->first('title') }}</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold" id="body-label">Content</label>
                <div @class(['post-editor', 'is-invalid' => $errors->has('body')])>
                    <div x-ref="editor" aria-labelledby="body-label"></div>
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
                <div class="field-error">{{ $errors->first('body') }}</div>
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

                    if (initial.body) {
                        quill.clipboard.dangerouslyPasteHTML(initial.body, 'silent');
                    }
                    quill.on('text-change', () => {
                        this.dirty = true;
                        this.stepError = '';
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
                    if (!file) return;

                    // The post title describes the image for screen readers and search engines, so writers are not asked.
                    const range = quill.getSelection(true);
                    this.uploadImage(file, range ? range.index : quill.getLength(), this.$refs.title.value.trim());
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

                // The server checks everything again; this only stops an obviously empty post from reaching step 2.
                goToOptions() {
                    if (this.$refs.title.value.trim() === '') {
                        this.step = 1;
                        this.stepError = 'Please add a title first.';
                        this.$refs.title.focus();
                        return;
                    }

                    if (quill.getText().trim() === '' && !quill.root.querySelector('img')) {
                        this.step = 1;
                        this.stepError = 'Please write some content first.';
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
