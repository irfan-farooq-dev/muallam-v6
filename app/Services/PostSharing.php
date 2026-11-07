<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostShare;
use App\Notifications\RestrictedPostInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Invitations to restricted posts: emailed links (only a hash of each token is stored)
 * plus the post password, remembered per browser session once entered.
 */
class PostSharing
{
    private const SESSION_INVITE = 'restricted_invites';

    private const SESSION_UNLOCKED = 'restricted_unlocked';

    /**
     * Invites each email (inviting someone again sends a fresh link). Returns how many emails were sent.
     *
     * @param  list<string>  $emails
     */
    public function invite(Post $post, array $emails): int
    {
        foreach ($emails as $email) {
            $share = $post->shares()->firstOrNew(['email' => $email]);
            $this->sendLink($post, $share);
        }

        return count($emails);
    }

    public function resend(PostShare $share): void
    {
        $this->sendLink($share->post, $share);
    }

    public function revoke(PostShare $share): void
    {
        $share->delete();
    }

    public function findByToken(Post $post, string $token): ?PostShare
    {
        return $post->shares()->where('token_hash', hash('sha256', $token))->first();
    }

    /**
     * Step 1: a valid invite link was opened in this browser.
     */
    public function rememberInvite(Request $request, PostShare $share): void
    {
        $request->session()->put(self::SESSION_INVITE.'.'.$share->post_id, $share->getKey());
    }

    public function inviteInSession(Request $request, Post $post): ?PostShare
    {
        $shareId = $request->session()->get(self::SESSION_INVITE.'.'.$post->getKey());

        return $shareId ? $post->shares()->find($shareId) : null;
    }

    /**
     * Step 2: the right password was entered for that invite.
     */
    public function unlock(Request $request, PostShare $share): void
    {
        $request->session()->put(self::SESSION_UNLOCKED.'.'.$share->post_id, $share->getKey());
        $share->forceFill(['last_accessed_at' => now()])->save();
    }

    /**
     * Whether this browser unlocked the post with an invite that has not been revoked since.
     */
    public function hasAccess(Request $request, Post $post): bool
    {
        $shareId = $request->session()->get(self::SESSION_UNLOCKED.'.'.$post->getKey());

        return $shareId !== null && $post->shares()->whereKey($shareId)->exists();
    }

    private function sendLink(Post $post, PostShare $share): void
    {
        $token = Str::random(48);
        $share->token_hash = hash('sha256', $token);
        $share->save();
        $share->touch();

        Notification::route('mail', $share->email)->notify(new RestrictedPostInvite($post, $token));
    }
}
