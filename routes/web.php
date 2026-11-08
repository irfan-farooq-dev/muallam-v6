<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Posts\CommentController;
use App\Http\Controllers\Posts\LikedPostsController;
use App\Http\Controllers\Posts\MyPostsController;
use App\Http\Controllers\Posts\PostAccessController;
use App\Http\Controllers\Posts\PostChangesController;
use App\Http\Controllers\Posts\PostController;
use App\Http\Controllers\Posts\PostEditorController;
use App\Http\Controllers\Posts\PostImageUploadController;
use App\Http\Controllers\Posts\PostLikeController;
use App\Http\Controllers\Posts\PostPreviewController;
use App\Http\Controllers\Posts\PostShareController;
use App\Http\Controllers\Profile\PasswordController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\ProfilePhotoController;
use App\Http\Controllers\Profile\SlugController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\UserBioController;
use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('search', SearchController::class)->name('search');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('category/{category}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('users/{slug}/bio', [UserBioController::class, 'show'])->name('users.bio');
Route::get('users/{slug}/posts', [UserBioController::class, 'posts'])->name('users.posts');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])
        ->middleware('throttle:register')
        ->name('register.store');

    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', LogoutController::class)->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/feed', [NotificationController::class, 'feed'])->middleware('throttle:30,1')->name('notifications.feed');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/photo', [ProfilePhotoController::class, 'update'])->middleware('throttle:10,1')->name('profile.photo.update');
    Route::delete('profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');
    Route::get('profile/slug/check', [SlugController::class, 'check'])->middleware('throttle:60,1')->name('profile.slug.check');
    Route::put('profile/slug', [SlugController::class, 'update'])->middleware('throttle:10,1')->name('profile.slug.update');
    Route::put('profile/password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('profile.password.update');

    Route::get('email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('my/posts', MyPostsController::class)->name('posts.mine');
    // The edit form spoofs PUT, so the preview button (which reuses that form) can arrive as POST or PUT.
    Route::match(['post', 'put'], 'posts/preview', PostPreviewController::class)->name('posts.preview');
    Route::get('posts/preview', fn () => to_route('posts.mine')->with('info', 'Previews open from the Preview button in the editor and are not saved.'));
    Route::post('posts/images', PostImageUploadController::class)->middleware('throttle:30,1')->name('posts.images.store');
    Route::get('posts/create', [PostEditorController::class, 'create'])->can('create', Post::class)->name('posts.create');
    Route::post('posts', [PostEditorController::class, 'store'])->can('create', Post::class)->name('posts.store');
    Route::get('posts/{post}/edit', [PostEditorController::class, 'edit'])->can('update', 'post')->name('posts.edit');
    Route::put('posts/{post}', [PostEditorController::class, 'update'])->can('update', 'post')->name('posts.update');
    Route::delete('posts/{post}', [PostEditorController::class, 'destroy'])->can('delete', 'post')->name('posts.destroy');
    Route::get('posts/{post}/changes', [PostChangesController::class, 'show'])->can('update', 'post')->name('posts.changes');
    Route::post('posts/{post}/changes/reviewed', [PostChangesController::class, 'markReviewed'])
        ->can('reviewAdminEdits', 'post')->name('posts.changes.reviewed');

    Route::post('posts/{post}/shares', [PostShareController::class, 'store'])
        ->can('update', 'post')->middleware('throttle:10,1')->name('posts.shares.store');
    Route::post('posts/{post}/shares/{share}/resend', [PostShareController::class, 'resend'])
        ->can('update', 'post')->middleware('throttle:10,1')->scopeBindings()->name('posts.shares.resend');
    Route::delete('posts/{post}/shares/{share}', [PostShareController::class, 'destroy'])
        ->can('update', 'post')->scopeBindings()->name('posts.shares.destroy');
});

Route::middleware('auth')->group(function () {
    Route::post('posts/{post}/like', [PostLikeController::class, 'toggle'])
        ->can('like', 'post')
        ->middleware('throttle:60,1')
        ->name('posts.like');
    Route::get('posts/{post}/like', [PostLikeController::class, 'signInToLike'])->name('posts.like.sign-in');
    Route::get('my/likes', LikedPostsController::class)->name('likes.index');

    Route::get('posts/{post}/comments', [CommentController::class, 'signInToComment'])->name('comments.sign-in');
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])
        ->middleware(['verified', 'throttle:10,1'])
        ->name('comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::patch('comments/{comment}/visibility', [CommentController::class, 'visibility'])->name('comments.visibility');
});

// Admin panel (Sneat). Admins only.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::resource('users', AdminUserController::class)->only(['index', 'edit', 'update', 'destroy']);
    Route::resource('categories', AdminCategoryController::class)->except(['show']);
    Route::post('categories/{category}/move', [AdminCategoryController::class, 'move'])->name('categories.move');
    Route::get('posts', [AdminPostController::class, 'index'])->name('posts.index');
    Route::post('posts/{post}/unpublish', [AdminPostController::class, 'unpublish'])->name('posts.unpublish');
    Route::delete('posts/{post}', [AdminPostController::class, 'destroy'])->name('posts.destroy');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('comments', [AdminCommentController::class, 'index'])->name('comments.index');
    Route::post('comments/bulk', [AdminCommentController::class, 'bulk'])->name('comments.bulk');
    Route::patch('comments/{comment}/visibility', [AdminCommentController::class, 'visibility'])->name('comments.visibility');
    Route::delete('comments/{comment}', [AdminCommentController::class, 'destroy'])->name('comments.destroy');
});

// Invited readers of restricted posts: invite link, then the post password.
Route::get('posts/{post}/access', [PostAccessController::class, 'show'])->name('posts.access');
Route::post('posts/{post}/access', [PostAccessController::class, 'unlock'])->middleware('throttle:post-access')->name('posts.access.unlock');

// Registered last so fixed addresses such as /posts/create are matched first.
Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');

/*
 * Addresses from the old muallam.com, sent permanently to their new home.
 */
Route::permanentRedirect('blogs', '/');
Route::permanentRedirect('blogs/{slug}', '/posts/{slug}')->where('slug', '[a-z0-9-]+');
Route::permanentRedirect('signup', '/register');
Route::permanentRedirect('{slug}/blogs', '/users/{slug}/posts')->where('slug', '[a-z0-9-]+');
