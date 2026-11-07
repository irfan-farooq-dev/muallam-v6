<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Services\UserSlugChanger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SlugController extends Controller
{
    public function __construct(private UserSlugChanger $slugs) {}

    /**
     * Live "is this available?" check used by the profile page.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate(['slug' => ['required', 'string', 'max:100']]);

        $slug = $this->slugs->normalize($request->string('slug'));
        $problem = $this->slugs->problemWith($request->user(), $slug);

        return response()->json([
            'slug' => $slug,
            'available' => $problem === null,
            'message' => $problem ?? 'Available',
            'url' => route('users.bio', $slug ?: '-'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validateWithBag('updateSlug', ['slug' => ['required', 'string', 'max:100']]);
        $user = $request->user();

        if ($nextChange = $this->slugs->nextChangeAllowedAt($user)) {
            return back()->withErrors(['slug' => "You can change your link again on {$nextChange->format('j M Y')}."], 'updateSlug');
        }

        $slug = $this->slugs->normalize($request->string('slug'));

        if ($problem = $this->slugs->change($user, $slug)) {
            return back()->withInput()->withErrors(['slug' => $problem], 'updateSlug');
        }

        return back()->with('success', 'Your profile link is now '.route('users.bio', $slug).'. Your old link will keep redirecting here.');
    }
}
