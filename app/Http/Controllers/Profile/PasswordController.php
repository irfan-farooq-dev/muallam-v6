<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Services\UserSessions;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, UserSessions $sessions): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        $request->session()->regenerate();
        $sessions->endOtherSessions($user, $request->session()->getId());

        return back()->with('success', 'Password changed. You have been signed out on your other devices.');
    }
}
