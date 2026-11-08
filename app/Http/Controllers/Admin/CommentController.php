<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Services\Notifier;
use App\Services\PostComments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Every comment on the site, for moderation: hide, show again or delete, one at a time or in bulk.
 */
class CommentController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * @var array<string, string>
     */
    public const STATUS_FILTERS = [
        'visible' => 'Visible',
        'hidden' => 'Hidden',
        'deleted' => 'Deleted',
    ];

    public function __construct(
        private PostComments $comments,
        private Notifier $notifier,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $author = trim((string) $request->query('author'));
        $status = array_key_exists((string) $request->query('status'), self::STATUS_FILTERS) ? (string) $request->query('status') : '';
        $post = $request->integer('post') ? Post::query()->find($request->integer('post'), ['id', 'title', 'slug']) : null;

        $comments = Comment::query()
            ->with(['user:id,name,email,slug,avatar', 'post:id,title,slug', 'parent:id,user_id', 'parent.user:id,name'])
            ->when($status === 'deleted', fn (Builder $query) => $query->onlyTrashed())
            ->when($status === 'visible', fn (Builder $query) => $query->where('is_approved', true))
            ->when($status === 'hidden', fn (Builder $query) => $query->where('is_approved', false))
            ->when($search !== '', fn (Builder $query) => $query->where('body', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($author !== '', function (Builder $query) use ($author): void {
                $like = '%'.addcslashes($author, '%_\\').'%';
                $query->whereHas('user', fn (Builder $user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($post, fn (Builder $query) => $query->whereBelongsTo($post))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.comments.index', [
            'comments' => $comments,
            'filteredPost' => $post,
            'filters' => ['search' => $search, 'author' => $author, 'status' => $status, 'post' => $post?->id],
        ]);
    }

    public function visibility(Request $request, Comment $comment): RedirectResponse
    {
        $visible = $request->boolean('visible');
        $this->changeVisibility($comment, $visible, $request);

        return back()->with('success', $visible ? 'Comment is visible again.' : 'Comment hidden from readers.');
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $this->notifier->commentDeletedByAdmin($comment, $request->user());
        $this->comments->remove($comment);

        return back()->with('success', 'Comment deleted.');
    }

    /**
     * The same action for every ticked comment.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['hide', 'show', 'delete'])],
            'comments' => ['required', 'array', 'max:'.self::PER_PAGE],
            'comments.*' => ['integer'],
        ], [
            'comments.required' => 'Tick at least one comment first.',
        ]);

        $selected = Comment::query()->with(['post:id,user_id,title,slug', 'user'])->whereKey($validated['comments'])->get();

        foreach ($selected as $comment) {
            if ($validated['action'] === 'delete') {
                $this->notifier->commentDeletedByAdmin($comment, $request->user());
                $this->comments->remove($comment);
            } else {
                $this->changeVisibility($comment, $validated['action'] === 'show', $request);
            }
        }

        $done = ['hide' => 'hidden', 'show' => 'made visible', 'delete' => 'deleted'][$validated['action']];

        return back()->with('success', trans_choice("{1} 1 comment {$done}.|[2,*] :count comments {$done}.", $selected->count()));
    }

    /**
     * Hides or shows a comment; its writer is told when it gets hidden.
     */
    private function changeVisibility(Comment $comment, bool $visible, Request $request): void
    {
        $wasVisible = $comment->is_approved;
        $this->comments->setVisible($comment, $visible);

        if ($wasVisible && ! $visible) {
            $this->notifier->commentHiddenByAdmin($comment, $request->user());
        }
    }
}
