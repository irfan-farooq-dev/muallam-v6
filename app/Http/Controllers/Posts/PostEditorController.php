<?php

namespace App\Http\Controllers\Posts;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\SavePostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Services\FeaturedImageProcessor;
use App\Services\PostSlugGenerator;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PostEditorController extends Controller
{
    public function __construct(private FeaturedImageProcessor $featuredImages) {}

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

        return redirect()->route('posts.edit', $post)->with('success', $this->savedMessage($post, 'created'));
    }

    public function edit(Post $post): View
    {
        return $this->form($post);
    }

    public function update(SavePostRequest $request, Post $post): RedirectResponse
    {
        $post->fill($this->postAttributes($request, $post));

        if ($request->boolean('remove_featured_image') || $request->hasFile('featured_image')) {
            $this->featuredImages->delete($post->featured_image);
            $post->featured_image = null;
        }

        if ($request->hasFile('featured_image')) {
            $post->featured_image = $this->featuredImages->store($post->user, $request->file('featured_image'));
        }

        $post->save();

        return redirect()->route('posts.edit', $post)->with('success', $this->savedMessage($post, 'saved'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->featuredImages->delete($post->featured_image);
        $post->delete();

        return redirect()->route('posts.mine')->with('success', "\"{$post->title}\" was deleted.");
    }

    private function form(Post $post): View
    {
        return view('member.posts.form', [
            'post' => $post,
            'categories' => Category::query()->active()->get(['id', 'name']),
            'statuses' => SavePostRequest::editableStatuses($post),
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
            $post->status === PostStatus::Restricted => "Post {$verb}. It is restricted, so only you can see it for now.",
            default => "Post {$verb} as a draft.",
        };
    }
}
