@extends('admin.layouts.app')

@section('title', 'Message')

@section('page_actions')
    <a href="{{ route('admin.messages.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bx bx-arrow-back me-1"></i>All messages</a>
@endsection

@section('content')
    @php
        $replySubject = 'Re: '.$message->subject->label().' ('.config('app.name').')';
        $replyBody = "\n\n---\nOn {$message->created_at->format('j M Y')} {$message->name} wrote:\n".$message->message;
    @endphp

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                <div>
                    <h5 class="mb-1">{{ $message->name }}</h5>
                    <div class="small"><a href="mailto:{{ $message->email }}">{{ $message->email }}</a></div>
                    @if ($message->user)
                        <div class="small text-muted mt-1">
                            Member: <a href="{{ route('admin.users.edit', $message->user) }}">{{ $message->user->name }}</a>
                        </div>
                    @endif
                </div>
                <div class="text-md-end small text-muted">
                    <span class="badge bg-label-secondary mb-1">{{ $message->subject->label() }}</span>
                    <div><x-local-time :at="$message->created_at" /></div>
                </div>
            </div>

            <div class="admin-message-body border rounded p-3 mb-4">{{ $message->message }}</div>

            <div class="d-flex flex-wrap gap-2">
                <a href="mailto:{{ $message->email }}?subject={{ rawurlencode($replySubject) }}&body={{ rawurlencode($replyBody) }}" class="btn btn-primary">
                    <i class="bx bx-reply me-1"></i>Reply by email
                </a>
                <form method="POST" action="{{ route('admin.messages.unread', $message) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary"><i class="bx bx-envelope me-1"></i>Mark as unread</button>
                </form>
                <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" class="ms-auto"
                      onsubmit="return confirm('Delete this message? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bx bx-trash me-1"></i>Delete</button>
                </form>
            </div>
        </div>
    </div>
@endsection
