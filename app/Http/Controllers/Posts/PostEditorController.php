<?php

namespace App\Http\Controllers\Posts;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\SavePostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\AdminEditTracker;
use App\Services\FeaturedImageProcessor;
use App\Services\Notifier;
use App\Services\PostImageStore;
use App\Services\PostSlugGenerator;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PostEditorController extends Controller
{
    public function __construct(
        private FeaturedImageProcessor $featuredImages,
        private PostImageStore $bodyImages,
        private AdminEditTracker $adminEdits,
        private Notifier $notifier,
    ) {}

    public function create(): View
    {
        return $this->form(new Post);
    }

    public function store(SavePostRequest $request, PostSlugGenerator $slugs): RedirectResponse
    {
        $post = new Post($this->postAttributes($request, new Post));
        $post->user()->associate($request->user());
        $post->slug = $slugs->generate($post->title);

        if ($request->hasFile('featured_image')) {
            $post->featured_image = $this->featuredImages->store($request->user(), $request->file('featured_image'));
        }

        $post->save();
        $this->bodyImages->syncWithBody($post, $request->user());

        if ($post->status === PostStatus::Published) {
            $this->notifier->postPublished($post, $request->user());
        }

        return redirect()->route('posts.edit', $post)->with('success', $this->savedMessage($post, 'created'));
    }

    public function edit(Post $post): View
    {
        return $this->form($post);
    }

    public function update(SavePostRequest $request, Post $post): RedirectResponse
    {
        $wasPublished = $post->status === PostStatus::Published;
        $wasLive = $wasPublished && ! $post->isScheduled();
        $before = $this->adminEdits->snapshot($post);
        $post->fill($this->postAttributes($request, $post));

        if ($request->boolean('remove_featured_image') || $request->hasFile('featured_image')) {
            $this->featuredImages->delete($post->featured_image);
            $post->featured_image = null;
        }

        if ($request->hasFile('featured_image')) {
            $post->featured_image = $this->featuredImages->store($post->user, $request->file('featured_image'));
        }

        $post->save();
        $this->bodyImages->syncWithBody($post, $request->user());
        $this->adminEdits->recordSave($post, $request->user(), $before);
        $this->notifyAboutStatusChange($post, $request->user(), $wasPublished, $wasLive);

        $message = $wasLive && $post->status === PostStatus::Draft
            ? 'Post unpublished. It is a draft again, so only you can see it.'
            : $this->savedMessage($post, 'saved');

        return redirect()->route('posts.edit', $post)->with('success', $message);
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            $this->notifier->postDeletedByAdmin($post, $request->user());
        }

        $this->featuredImages->delete($post->featured_image);
        $this->bodyImages->deleteAllFor($post);
        $post->delete();

        $backTo = $request->user()->is($post->user) ? 'posts.mine' : 'admin.posts.index';

        return redirect()->route($backTo)->with('success', "\"{$post->title}\" was deleted.");
    }

    /**
     * Admins hear about newly published posts; an author hears when an admin takes their live post down.
     */
    private function notifyAboutStatusChange(Post $post, User $editor, bool $wasPublished, bool $wasLive): void
    {
        if (! $wasPublished && $post->status === PostStatus::Published) {
            $this->notifier->postPublished($post, $editor);
        }

        if ($wasLive && $post->status === PostStatus::Draft && $editor->isAdmin()) {
            $this->notifier->postUnpublishedByAdmin($post, $editor);
        }
    }

    private function form(Post $post): View
    {
        return view('member.posts.form', [
            'post' => $post,
            'pendingAdminEdit' => $post->exists ? $post->pendingAdminEdit()->with('editor:id,name')->first() : null,
            'shares' => $post->exists ? $post->shares()->latest()->get() : collect(),
            'categories' => Category::query()->active()->get(['id', 'name']),
            'defaultCategoryId' => Category::default()?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postAttributes(SavePostRequest $request, Post $post): array
    {
        $status = PostStatus::from($request->validated('status'));

        return [
            ...$request->safe()->only(['title', 'summary', 'category_id', 'body', 'allow_comments']),
            ...($request->filled('access_password') ? ['access_password' => $request->validated('access_password')] : []),
            'status' => $status,
            'published_at' => $this->publishedAt($request, $post, $status),
        ];
    }

    /**
     * Only published posts carry a publish time. A post that is already live keeps its original date when edited.
     */
    private function publishedAt(SavePostRequest $request, Post $post, PostStatus $status): ?CarbonInterface
    {
        if ($status !== PostStatus::Published) {
            return null;
        }

        if ($request->wantsSchedule()) {
            return Carbon::parse($request->validated('scheduled_at'));
        }

        $alreadyLive = $post->exists && $post->status === PostStatus::Published && $post->published_at?->isPast();

        return $alreadyLive ? $post->published_at : now();
    }

    private function savedMessage(Post $post, string $verb): string
    {
        return match (true) {
            $post->isScheduled() => "Post {$verb}. It will go live on {$post->published_at->format('j M Y, g:i a')}.",
            $post->status === PostStatus::Published => "Post {$verb} and published.",
            $post->status === PostStatus::Private => "Post {$verb}. It is private, so only you can see it.",
            $post->status === PostStatus::Restricted => "Post {$verb}. It is restricted: only people you invite (with the password) can read it.",
            default => "Post {$verb} as a draft.",
        };
    }
}
