<?php

namespace App\Http\Controllers\Profile;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\UserSlugChanger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request, UserSlugChanger $slugs): View
    {
        return view('member.profile', [
            'user' => $request->user(),
            'genders' => Gender::cases(),
            'nextSlugChange' => $slugs->nextChangeAllowedAt($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->except('current_password'));

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return back()->with('success', "Profile saved. We've sent a verification link to {$user->email}.");
        }

        return back()->with('success', 'Profile saved.');
    }
}
