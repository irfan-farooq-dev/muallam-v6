<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\UserSlugGenerator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * How often to retry when another signup grabs the same slug at the same moment.
     */
    private const SLUG_ATTEMPTS = 3;

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, UserSlugGenerator $slugs): RedirectResponse
    {
        $user = $this->createUser($request->validated(), $slugs);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "Welcome to Muallam, {$user->name}! We've sent a verification link to {$user->email}.");
    }

    /**
     * Only the validated fields are used, so extra form fields such as "role" are ignored.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    private function createUser(array $data, UserSlugGenerator $slugs): User
    {
        for ($attempt = 1; ; $attempt++) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->slug = $slugs->generate($data['name']);

            try {
                $user->save();

                return $user;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::SLUG_ATTEMPTS || User::where('email', $data['email'])->exists()) {
                    throw $exception;
                }
            }
        }
    }
}
