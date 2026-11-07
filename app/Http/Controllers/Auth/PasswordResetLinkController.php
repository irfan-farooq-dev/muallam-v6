<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Shown whatever happened, so the form never reveals which emails have accounts.
     */
    public const SENT_MESSAGE = 'If an account exists for that email, we have sent a password reset link. It expires in 60 minutes.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        // Possible results: link sent, unknown email, or throttled (one email per address per minute). All look the same to the visitor.
        Password::sendResetLink($request->only('email'));

        return back()->with('success', self::SENT_MESSAGE);
    }
}
