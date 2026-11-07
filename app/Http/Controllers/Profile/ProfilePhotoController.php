<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfilePhotoRequest;
use App\Services\AvatarProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfilePhotoController extends Controller
{
    public function update(UpdateProfilePhotoRequest $request, AvatarProcessor $avatars): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['avatar' => $avatars->replace($user, $request->file('photo'))])->save();

        return back()->with('success', 'Profile photo updated.');
    }

    public function destroy(Request $request, AvatarProcessor $avatars): RedirectResponse
    {
        $user = $request->user();
        $avatars->delete($user->avatar);
        $user->forceFill(['avatar' => null])->save();

        return back()->with('success', 'Profile photo removed.');
    }
}
