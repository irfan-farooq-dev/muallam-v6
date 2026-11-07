{{-- Invite people to a restricted post. Shown on the edit page once the post is saved as restricted. --}}
<div class="card settings-card mt-4" id="sharing">
    <div class="card-body p-4">
        <h3 class="h5 mb-1">Sharing</h3>
        <p class="text-secondary small mb-3">
            Each person gets an email with a private link. They also need the post password, which you tell them yourself.
            Revoking someone ends their access straight away.
        </p>

        <form method="POST" action="{{ route('posts.shares.store', $post) }}" class="mb-4">
            @csrf
            <label for="share_emails" class="form-label small text-secondary mb-1">Email addresses (one per line, or separated by commas)</label>
            <textarea id="share_emails" name="emails" rows="3" class="form-control mb-2 @if ($errors->sharing->any()) is-invalid @endif"
                      placeholder="friend@example.com, colleague@example.com">{{ old('emails') }}</textarea>
            @if ($errors->sharing->any())
                <div class="field-error mb-2">{{ $errors->sharing->first() }}</div>
            @endif
            <button type="submit" class="btn btn-accent btn-sm"><i class="bi bi-send me-1"></i>Send invites</button>
        </form>

        @if ($shares->isEmpty())
            <p class="text-secondary small mb-0">Nobody has been invited yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="small text-secondary">
                            <th>Email</th>
                            <th>Invited</th>
                            <th>Last opened</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shares as $share)
                            <tr>
                                <td class="text-break">{{ $share->email }}</td>
                                <td class="small text-secondary">{{ $share->updated_at->diffForHumans() }}</td>
                                <td class="small text-secondary">{{ $share->last_accessed_at?->diffForHumans() ?? 'Not yet' }}</td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" action="{{ route('posts.shares.resend', [$post, $share]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm p-0 me-2">Resend</button>
                                    </form>
                                    <form method="POST" action="{{ route('posts.shares.destroy', [$post, $share]) }}" class="d-inline"
                                          onsubmit="return confirm(@js('Revoke access for '.$share->email.'?'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
