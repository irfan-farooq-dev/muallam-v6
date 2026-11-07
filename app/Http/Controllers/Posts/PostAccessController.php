<?php

namespace App\Http\Controllers\Posts;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PostSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * How an invited reader opens a restricted post: invite link first, then the post password.
 */
class PostAccessController extends Controller
{
    public function __construct(private PostSharing $sharing) {}

    public function show(Request $request, Post $post): View|RedirectResponse
    {
        if ($post->status !== PostStatus::Restricted || $this->sharing->hasAccess($request, $post)) {
            return redirect()->route('posts.show', $post);
        }

        if ($request->filled('token')) {
            $share = $this->sharing->findByToken($post, (string) $request->query('token'));
            abort_unless($share, 404);

            $this->sharing->rememberInvite($request, $share);

            // Reload without the token so it does not stay in the address bar or browser history.
            return redirect()->route('posts.access', $post);
        }

        abort_unless($this->sharing->inviteInSession($request, $post), 404);

        return view('posts.access', ['post' => $post]);
    }

    public function unlock(Request $request, Post $post): RedirectResponse
    {
        $share = $post->status === PostStatus::Restricted ? $this->sharing->inviteInSession($request, $post) : null;
        abort_unless($share, 404);

        $request->validateWithBag('access', ['password' => ['required', 'string', 'max:255']]);

        if (! $post->access_password || ! Hash::check($request->input('password'), $post->access_password)) {
            return back()->withErrors(['password' => 'That password is not right. Ask '.$post->user->name.' for it.'], 'access');
        }

        $this->sharing->unlock($request, $share);

        return redirect()->route('posts.show', $post);
    }
}
