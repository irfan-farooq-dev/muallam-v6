@extends('layouts.member')

@section('title', 'Changes by an admin')

@section('page_actions')
    <a href="{{ route('posts.edit', $post) }}" class="btn btn-link text-secondary"><i class="bi bi-arrow-left me-1"></i>Back to the editor</a>
@endsection

@section('member_content')
    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>
            <i class="bi bi-person-gear me-1"></i>
            <strong>{{ $edit->editor?->name ?? 'An admin' }}</strong> edited
            @if ($isAuthor) your post @else {{ $post->user->name }}'s post @endif
            {{ $edit->updated_at->diffForHumans() }}.
            Only {{ $isAuthor ? 'you' : 'the author' }} and admins see these highlights; readers see the plain post.
        </span>
        @if ($isAuthor)
            <form method="POST" action="{{ route('posts.changes.reviewed', $post) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-accent"><i class="bi bi-check2 me-1"></i>Mark as reviewed</button>
            </form>
        @endif
    </div>

    <p class="small text-secondary mb-4">
        <ins class="diff-added">Added words</ins> &middot; <del class="diff-removed">removed words</del>.
        Compared with {{ $isAuthor ? 'your' : "the author's" }} version from {{ $edit->created_at->format('j M Y, g:i a') }}. Formatting changes (bold, links) are not shown.
        @if ($isAuthor)
            Saving the post yourself also marks the changes as reviewed.
        @endif
    </p>

    <div class="card mb-4">
        <div class="card-body post-diff">
            <div class="small text-uppercase text-secondary mb-1">Title</div>
            <h2 class="h4 mb-4">@include('member.posts.partials.diff-text', ['segments' => $title])</h2>

            <div class="small text-uppercase text-secondary mb-1">Summary</div>
            <p class="mb-4">
                @if ($summary)
                    @include('member.posts.partials.diff-text', ['segments' => $summary])
                @else
                    <span class="text-secondary fst-italic">No summary</span>
                @endif
            </p>

            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <div class="small text-uppercase text-secondary">Text</div>
                <div class="small text-secondary" data-changed-blocks="{{ $changedBlocks }}">
                    {{ trans_choice('{0} No paragraphs changed|{1} 1 paragraph changed|[2,*] :count paragraphs changed', $changedBlocks) }}
                </div>
            </div>
            @forelse ($body as $block)
                @php
                    $isHeading = preg_match('/^h[1-6]$/', $block['tag']) === 1;
                @endphp
                <div @class([
                    'diff-block',
                    'diff-block-'.$block['status'],
                    'diff-heading' => $isHeading,
                    'diff-list-item' => $block['tag'] === 'li',
                    'diff-quote' => $block['tag'] === 'blockquote',
                ])>@include('member.posts.partials.diff-text', ['segments' => $block['segments']])</div>
            @empty
                <p class="text-secondary fst-italic">No text.</p>
            @endforelse
        </div>
    </div>

    <a href="{{ route('posts.edit', $post) }}" class="btn btn-outline-accent"><i class="bi bi-pencil me-1"></i>Open in the editor</a>
    <a href="{{ route('posts.show', $post) }}" class="btn btn-link text-secondary" target="_blank">View post</a>
@endsection
