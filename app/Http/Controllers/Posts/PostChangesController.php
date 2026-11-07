<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AdminEditTracker;
use App\Services\PostDiff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shows the author (and admins) what an admin changed in a post, word by word. Readers never see this page.
 */
class PostChangesController extends Controller
{
    public function show(Request $request, Post $post, PostDiff $diff): View|RedirectResponse
    {
        $edit = $post->pendingAdminEdit()->with('editor:id,name')->first();

        if (! $edit) {
            return to_route('posts.edit', $post)->with('info', 'There are no admin changes waiting for review on this post.');
        }

        $body = $diff->body($edit->previous_body, (string) $post->body);

        return view('member.posts.changes', [
            'post' => $post,
            'edit' => $edit,
            'title' => $diff->words($edit->previous_title, $post->title),
            'summary' => $diff->words((string) $edit->previous_summary, (string) $post->summary),
            'body' => $body,
            'changedBlocks' => collect($body)->where('status', '!=', 'same')->count(),
            'isAuthor' => $request->user()->can('reviewAdminEdits', $post),
        ]);
    }

    public function markReviewed(Post $post, AdminEditTracker $adminEdits): RedirectResponse
    {
        $adminEdits->resolve($post);

        return to_route('posts.edit', $post)->with('success', 'Admin changes marked as reviewed.');
    }
}
