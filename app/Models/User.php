<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'phone', 'gender', 'bio', 'is_bio_public', 'show_online_status', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'gender' => Gender::class,
            'is_bio_public' => 'boolean',
            'is_active' => 'boolean',
            'slug_changed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'show_online_status' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<Like, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return HasMany<PostImage, $this>
     */
    public function postImages(): HasMany
    {
        return $this->hasMany(PostImage::class);
    }

    /**
     * @return HasMany<UserSlugHistory, $this>
     */
    public function slugHistory(): HasMany
    {
        return $this->hasMany(UserSlugHistory::class);
    }

    /**
     * Public URL of the profile photo, or null when the user has none.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Did something on the site within the last few minutes (config muallam.online_minutes).
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(now()->subMinutes(config('muallam.online_minutes')));
    }

    /**
     * Whether $viewer may see this member's online status: everyone, unless the member turned it off
     * (they always see their own).
     */
    public function sharesOnlineStatusWith(?User $viewer): bool
    {
        return $this->show_online_status || ($viewer !== null && $viewer->is($this));
    }

    /**
     * Green dot on public pages.
     */
    public function appearsOnlineTo(?User $viewer): bool
    {
        return $this->sharesOnlineStatusWith($viewer) && $this->isOnline();
    }

    /**
     * "Active now" / "Last seen 3 hours ago", or null when hidden or never seen.
     */
    public function presenceFor(?User $viewer): ?string
    {
        if (! $this->sharesOnlineStatusWith($viewer) || $this->last_seen_at === null) {
            return null;
        }

        return $this->isOnline() ? 'Active now' : 'Last seen '.$this->last_seen_at->diffForHumans();
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function online(Builder $query): void
    {
        $query->where('last_seen_at', '>', now()->subMinutes(config('muallam.online_minutes')));
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
