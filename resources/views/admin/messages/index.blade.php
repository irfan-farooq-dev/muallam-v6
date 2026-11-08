@extends('admin.layouts.app')

@section('title', 'Messages')

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.messages.index') }}" class="row g-2 align-items-end" role="search">
                <div class="col-md-5">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Name, email or message">
                </div>
                <div class="col-6 col-md-2">
                    <label for="status" class="form-label small">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="unread" @selected($filters['status'] === 'unread')>Unread</option>
                        <option value="read" @selected($filters['status'] === 'read')>Read</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="subject" class="form-label small">Subject</label>
                    <select id="subject" name="subject" class="form-select">
                        <option value="">Any</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->value }}" @selected($filters['subject'] === $subject->value)>{{ $subject->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    @if (array_filter($filters))
                        <a href="{{ route('admin.messages.index') }}" class="btn btn-outline-secondary" title="Clear filters"><i class="bx bx-x"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Received</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($messages as $message)
                        <tr @class(['message-unread' => $message->read_at === null])>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.messages.show', $message) }}" class="text-body {{ $message->read_at === null ? 'fw-bold' : '' }}">{{ $message->name }}</a>
                                @if ($message->read_at === null)
                                    <span class="badge bg-label-primary ms-1">New</span>
                                @endif
                                <div class="small text-muted">{{ $message->email }}</div>
                            </td>
                            <td class="text-nowrap"><span class="badge bg-label-secondary">{{ $message->subject->label() }}</span></td>
                            <td style="min-width: 260px;">
                                <a href="{{ route('admin.messages.show', $message) }}" class="text-body small">{{ \Illuminate\Support\Str::limit($message->message, 110) }}</a>
                            </td>
                            <td class="text-nowrap small"><span title="{{ $message->created_at->format('j M Y, g:i a') }}">{{ $message->created_at->diffForHumans() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No messages.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($messages->hasPages())
            <div class="card-body border-top">{{ $messages->links() }}</div>
        @endif
    </div>
    <p class="small text-muted mt-2 mb-0">
        {{ trans_choice('{1} 1 message|[0,*] :count messages', $messages->total()) }}
        &middot; {{ $unreadCount }} unread. Messages come from the <a href="{{ route('contact') }}" target="_blank">contact page</a>.
    </p>
@endsection
