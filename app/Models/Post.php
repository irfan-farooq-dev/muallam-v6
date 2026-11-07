<?php

namespace App\Models;

use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'category_id',
    'title',
    'slug',
    'summary',
    'body',
    'featured_image',
    'status',
    'access_password',
    'allow_comments',
    'published_at',
])]
#[Hidden(['access_password'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /**
     * Default values visible before the post is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'allow_comments' => true,
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'access_password' => 'hashed',
            'allow_comments' => 'boolean',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'views_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class);
    }

    /**
     * @return HasMany<PostShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(PostShare::class);
    }

    /**
     * @return HasMany<PostAdminEdit, $this>
     */
    public function adminEdits(): HasMany
    {
        return $this->hasMany(PostAdminEdit::class);
    }

    /**
     * Admin changes the author has not reviewed yet (at most one open record per post).
     *
     * @return HasOne<PostAdminEdit, $this>
     */
    public function pendingAdminEdit(): HasOne
    {
        return $this->hasOne(PostAdminEdit::class)->whereNull('resolved_at');
    }

    /**
     * Posts any visitor may see: published, with a publish time that has passed.
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function featuredImageUrl(): ?string
    {
        return $this->featured_image ? Storage::disk('public')->url($this->featured_image) : null;
    }

    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Published
            && $this->published_at?->isFuture() === true;
    }
}
