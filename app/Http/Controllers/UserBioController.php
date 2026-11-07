<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSlugHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserBioController extends Controller
{
    private const POSTS_PER_PAGE = 12;

    /**
     * Public bio page. Old slugs redirect permanently to the current one.
     */
    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $user = User::where('slug', $slug)->first();

        if (! $user) {
            return $this->redirectRetiredSlug($slug, 'users.bio');
        }

        $isOwner = $request->user()?->is($user) ?? false;

        abort_unless($user->is_active || $isOwner, 404);
        abort_unless($user->is_bio_public || $isOwner, 404);

        return view('users.bio', [
            'profileUser' => $user,
            'isOwner' => $isOwner,
        ]);
    }

    /**
     * An author's public posts. Shown even when their bio is hidden; only the name and photo appear then.
     */
    public function posts(Request $request, string $slug): View|RedirectResponse
    {
        $user = User::where('slug', $slug)->first();

        if (! $user) {
            return $this->redirectRetiredSlug($slug, 'users.posts');
        }

        abort_unless($user->is_active, 404);

        return view('users.posts', [
            'author' => $user,
            'posts' => $user->posts()
                ->publiclyVisible()
                ->with('category:id,name,slug')
                ->latest('published_at')
                ->paginate(self::POSTS_PER_PAGE),
        ]);
    }

    /**
     * Old slugs keep working: they redirect permanently to the same page under the current slug.
     */
    private function redirectRetiredSlug(string $slug, string $routeName): RedirectResponse
    {
        $retired = UserSlugHistory::with('user:id,slug')->where('slug', $slug)->first();
        abort_unless($retired?->user, 404);

        return redirect()->route($routeName, $retired->user->slug, 301);
    }
}
