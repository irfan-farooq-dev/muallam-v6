<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\UserSessions;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request, UserSessions $sessions): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($sessions): void {
                $user->forceFill(['password' => $password]);

                // Opening the emailed link proves the person owns this address.
                if (! $user->hasVerifiedEmail()) {
                    $user->email_verified_at = now();
                }

                $user->save();

                // Ends sessions an attacker may have opened with the old password, and old "remember me" cookies.
                $sessions->endOtherSessions($user);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This password reset link is invalid or has expired. Please request a new one.']);
        }

        return redirect()->route('login')
            ->with('success', 'Your password has been changed. Please sign in with your new password.');
    }
}
