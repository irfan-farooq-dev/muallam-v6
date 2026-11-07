<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\UserDeletion;
use App\Services\UserSessions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $filters = $request->only(['role', 'status', 'verified']);

        $users = User::query()
            ->withCount('posts')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $match) => $match->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('slug', 'like', $like));
            })
            ->when(in_array($filters['role'] ?? null, array_column(UserRole::cases(), 'value'), true), fn (Builder $query) => $query->where('role', $filters['role']))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when(($filters['verified'] ?? null) === 'yes', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when(($filters['verified'] ?? null) === 'no', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'search' => $search, 'filters' => $filters, 'roles' => UserRole::cases()]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user->loadCount(['posts', 'likes', 'comments']),
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserSessions $sessions): RedirectResponse
    {
        $wasActive = $user->is_active;

        $user->forceFill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => UserRole::from($request->validated('role')),
            'is_active' => $request->validated('is_active'),
            'email_verified_at' => $request->validated('email_verified') ? ($user->email_verified_at ?? now()) : null,
        ])->save();

        if ($wasActive && ! $user->is_active) {
            // Signed out everywhere straight away; the SignOutDeactivatedUsers middleware also catches any request in flight.
            $sessions->endOtherSessions($user);

            return redirect()->route('admin.users.edit', $user)->with('success', "{$user->name} is deactivated and has been signed out everywhere.");
        }

        return redirect()->route('admin.users.edit', $user)->with('success', 'User saved.');
    }

    public function destroy(Request $request, User $user, UserDeletion $deletion): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account here.');
        }

        if ($user->isAdmin() && User::where('role', UserRole::Admin)->where('is_active', true)->whereKeyNot($user->getKey())->doesntExist()) {
            return back()->with('error', 'This is the only active admin. Make someone else an admin first.');
        }

        $request->validate(['confirm_email' => ['required', 'in:'.$user->email]], ['confirm_email.in' => 'Type the user\'s email exactly to confirm.']);

        $name = $user->name;
        $summary = $deletion->delete($user);

        return redirect()->route('admin.users.index')->with('success', "{$name} was deleted, with {$summary['posts']} posts, {$summary['comments']} comments and {$summary['likes']} likes.");
    }
}
