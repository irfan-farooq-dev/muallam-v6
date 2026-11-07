<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSlugHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserBioController extends Controller
{
    /**
     * Public bio page. Old slugs redirect permanently to the current one.
     */
    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $user = User::where('slug', $slug)->first();

        if (! $user) {
            $retired = UserSlugHistory::with('user:id,slug')->where('slug', $slug)->first();
            abort_unless($retired?->user, 404);

            return redirect()->route('users.bio', $retired->user->slug, 301);
        }

        $isOwner = $request->user()?->is($user) ?? false;

        abort_unless($user->is_active || $isOwner, 404);
        abort_unless($user->is_bio_public || $isOwner, 404);

        return view('users.bio', [
            'profileUser' => $user,
            'isOwner' => $isOwner,
        ]);
    }
}
