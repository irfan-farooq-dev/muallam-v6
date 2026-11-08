# Muallam database design

Status: **draft for review**. Migrations are written only after this is approved.

Conventions
- MySQL, `utf8mb4_0900_ai_ci` (case-insensitive, so `Irfan-Farooq` and `irfan-farooq` collide).
- `role` and `status` are `varchar` columns validated by PHP backed enums, not MySQL `ENUM`, so new values need no schema change.
- Counters (`likes_count`, `views_count`, `comments_count`) are stored on `posts` and updated in the same transaction as the row that changes them.

## users

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(100) | |
| slug | varchar(60), unique | `irfan-farooq`, then `irfan-farooq-2`, ... |
| email | varchar(255), unique | |
| email_verified_at | timestamp, null | |
| password | varchar(255) | hashed |
| role | varchar(20), default `author` | `admin`, `author` |
| bio | text, null | |
| is_bio_public | boolean, default false | enables `/users/{slug}/bio` |
| avatar | varchar(255), null | path on the public disk |
| slug_changed_at | timestamp, null | one change per 30 days |
| is_active | boolean, default true | admin can block a user |
| last_seen_at | timestamp, null, indexed | last activity (page view or the bell's once-a-minute check), written at most once a minute; "online" = within `muallam.online_minutes` (5) |
| show_online_status | boolean, default true | member can hide their green dot and "Last seen" from others (admins still see it) |
| remember_token | varchar(100), null | |
| created_at, updated_at | timestamps | |

Slug rules: lowercase letters, digits and single hyphens; 3–50 characters; must not be a reserved word (`admin`, `api`, `login`, `register`, `logout`, `settings`, `profile`, `bio`, `users`, `posts`, `category`, ...); must not be any user's current slug **or** any slug in `user_slug_history`.

## user_slug_history

Old slugs keep redirecting (301) to the user's current profile and can never be claimed by anyone else.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users, cascade on delete | |
| slug | varchar(60), unique | the retired slug |
| created_at | timestamp | when it was retired |

If a user later switches back to one of their own old slugs, that history row is deleted.

## categories

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(100), unique | |
| slug | varchar(120), unique | `/category/{slug}` |
| description | text, null | |
| sort_order | smallint unsigned, default 0 | |
| is_active | boolean, default true | |
| created_at, updated_at | timestamps | |

## posts

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users, cascade on delete | |
| category_id | FK → categories, null, null on delete | |
| title | varchar(200) | |
| slug | varchar(220), unique | set once at creation; never regenerated from title changes |
| summary | varchar(500), null | |
| body | longtext | HTML from Quill, sanitized on save |
| featured_image | varchar(255), null | |
| status | varchar(20), default `draft` | `draft`, `published`, `private`, `restricted` |
| access_password | varchar(255), null | hashed; required when `restricted` |
| allow_comments | boolean, default true | owner can switch comments off per post |
| likes_count | int unsigned, default 0 | |
| comments_count | int unsigned, default 0 | approved, non-deleted comments |
| views_count | int unsigned, default 0 | one count per session per post |
| published_at | timestamp, null | a future value means "scheduled" |
| created_at, updated_at | timestamps | |

Indexes: `(status, published_at)`, `(user_id, status)`, `category_id`.

Public visibility: `status = 'published' AND published_at <= NOW()`.

## post_images

Images placed inside the post body (diagrams, sketches, screenshots). The featured image stays in `posts.featured_image`.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users, cascade on delete | uploader; used for upload limits |
| post_id | FK → posts, null, cascade on delete | null until the post is saved |
| path | varchar(255) | `posts/{user_id}/{uuid}.webp` on the public disk |
| alt | varchar(255), null | |
| width | smallint unsigned | |
| height | smallint unsigned | |
| size | int unsigned | bytes after compression |
| created_at, updated_at | timestamps | |

Indexes: `post_id`, `(user_id, post_id)`.

Flow:
1. Quill's image handler uploads the file to `POST /posts/images` instead of embedding base64 in the body.
2. The server accepts jpg/png/webp/gif up to 5 MB, resizes to max 1600px wide, converts to WebP (Intervention Image) and returns the URL; Quill inserts `<img src alt width height>`.
3. On post save, image URLs found in `body` are attached to the post (`post_id` set).
4. A daily job deletes files and rows for images still unattached after 24 hours and images no longer referenced in their post's body.

The featured image goes through the same resize step but is stored only as a path on `posts`.

## likes

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users, cascade on delete | |
| post_id | FK → posts, cascade on delete | |
| created_at | timestamp | |

Unique `(user_id, post_id)`; index `post_id`.

## comments

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| post_id | FK → posts, cascade on delete | |
| user_id | FK → users, cascade on delete | only signed-in users can comment |
| parent_id | FK → comments, null, cascade on delete | one level of replies |
| body | text | plain text, max 2000 characters |
| is_approved | boolean, default true | admin/owner can hide a comment |
| created_at, updated_at | timestamps | |
| deleted_at | timestamp, null | soft delete, so replies stay readable ("comment removed") |

Index `(post_id, parent_id, created_at)`.

## post_shares

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| post_id | FK → posts, cascade on delete | |
| email | varchar(255) | |
| token_hash | char(64), unique | SHA-256 of the invite token; the raw token exists only in the email |
| last_accessed_at | timestamp, null | |
| created_at, updated_at | timestamps | |

Unique `(post_id, email)`. Revoking access deletes the row.

Restricted flow: invite email link `/posts/{slug}/access?token=…` → token matched by hash → visitor enters the post password → session key `unlocked_post_{id}` grants access. No account needed.

## post_admin_edits

The author's version of a post from just before an admin changed its words, so the author can see what changed (word-by-word highlights, shown only to the author and admins). Readers always see the plain current post.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| post_id | FK → posts, cascade on delete | |
| editor_id | FK → users, null, null on delete | the admin who last changed it |
| previous_title | varchar(255) | |
| previous_summary | text, null | |
| previous_body | longtext | |
| resolved_at | timestamp, null | set when the author marks it reviewed or saves the post |
| created_at, updated_at | timestamps | updated_at = latest admin change |

Indexes: `(post_id, resolved_at)`.

Rules: at most one open row per post. Further admin edits keep the first row (so the comparison stays against the author's own version). Only title, summary and body changes open a row.

## contact_messages

Messages sent from the contact page (`/contact-us`), read by admins in Admin > Messages. Admins reply from their own email.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users, null, null on delete | set when the sender was signed in |
| name | varchar(100) | |
| email | varchar(255) | |
| subject | varchar(30) | `ContactSubject` enum: general_feedback, suggestion, complaint, marketing, technical_issue |
| message | text | max 5000 characters |
| ip_address | varchar(45), null | for blocking abuse |
| read_at | timestamp, null | set when an admin opens it |
| created_at, updated_at | timestamps | |

Indexes: `(read_at, created_at)`.

## notifications

Laravel's standard database notifications table (`php artisan make:notifications-table`), shown by the bell in the site header and the admin navbar. In-site only, no emails.

| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| type | varchar | always `App\Notifications\SiteNotification` |
| notifiable_type, notifiable_id | morph | the user it is for |
| data | text (JSON) | `kind` (`NotificationKind`), `message`, `excerpt`, `url` (site-relative), plus `post_id` / `comment_id` / `actor_ids` |
| read_at | timestamp, null | |
| created_at, updated_at | timestamps | |

Rules: nobody is notified of their own actions. Likes and admin edits on the same post update the existing unread notification ("Ali and 2 others liked…"). Deleting a user deletes their notifications; `notifications:prune` (daily) deletes ones read more than 90 days ago.

## Laravel default tables

`password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

## Deferred

- `post_views` log (per-view rows, like the old `blog_views`)
- `personal_access_tokens` (Sanctum) and `users.fcm_token` for the mobile app
- `notifications`
- Serving images of private/restricted posts through a permission-checking controller (for now they use unguessable names on the public disk)
- Image captions (needs a custom Quill extension; alt text is supported now)

## Relationships

```
users 1─* posts *─1 categories
users 1─* user_slug_history
users 1─* likes *─1 posts
users 1─* comments *─1 posts
comments 1─* comments (replies via parent_id)
posts 1─* post_shares
posts 1─* post_images *─1 users (uploader)
posts 1─* post_admin_edits *─1 users (editor)
```
