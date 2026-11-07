<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostShare;
use App\Services\PostSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The author manages who is invited to a restricted post.
 */
class PostShareController extends Controller
{
    public const MAX_PER_REQUEST = 20;

    public const MAX_PER_POST = 200;

    public function __construct(private PostSharing $sharing) {}

    public function store(Request $request, Post $post): RedirectResponse
    {
        $emails = $this->parseEmails($request, $post);
        $count = $this->sharing->invite($post, $emails);

        return redirect()->to(route('posts.edit', $post).'#sharing')
            ->with('success', trans_choice('{1} Invite sent to :emails.|[2,*] :count invites sent.', $count, ['emails' => $emails[0] ?? '']));
    }

    public function resend(Post $post, PostShare $share): RedirectResponse
    {
        $this->sharing->resend($share);

        return redirect()->to(route('posts.edit', $post).'#sharing')->with('success', "A new link was sent to {$share->email}. The old link no longer works.");
    }

    public function destroy(Post $post, PostShare $share): RedirectResponse
    {
        $this->sharing->revoke($share);

        return redirect()->to(route('posts.edit', $post).'#sharing')->with('success', "{$share->email} can no longer open this post.");
    }

    /**
     * Splits the textarea into unique, lower-case, valid email addresses.
     *
     * @return list<string>
     *
     * @throws ValidationException
     */
    private function parseEmails(Request $request, Post $post): array
    {
        $emails = collect(preg_split('/[\s,;]+/', (string) $request->input('emails')))
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->filter()
            ->unique()
            ->values();

        $fail = fn (string $message) => throw ValidationException::withMessages(['emails' => $message])->errorBag('sharing');

        if ($emails->isEmpty()) {
            $fail('Enter at least one email address.');
        }

        if ($emails->count() > self::MAX_PER_REQUEST) {
            $fail('You can invite up to '.self::MAX_PER_REQUEST.' people at a time.');
        }

        $invalid = $emails->reject(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
        if ($invalid->isNotEmpty()) {
            $fail('These are not valid email addresses: '.$invalid->implode(', '));
        }

        if ($emails->contains(Str::lower($post->user->email))) {
            $fail('You do not need to invite yourself.');
        }

        $newCount = $emails->diff($post->shares()->pluck('email'))->count();
        if ($post->shares()->count() + $newCount > self::MAX_PER_POST) {
            $fail('A post can be shared with at most '.self::MAX_PER_POST.' people.');
        }

        return $emails->all();
    }
}
