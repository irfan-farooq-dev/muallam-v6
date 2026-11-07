<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Posts\CommentController;
use App\Http\Controllers\Posts\LikedPostsController;
use App\Http\Controllers\Posts\MyPostsController;
use App\Http\Controllers\Posts\PostController;
use App\Http\Controllers\Posts\PostEditorController;
use App\Http\Controllers\Posts\PostImageUploadController;
use App\Http\Controllers\Posts\PostLikeController;
use App\Http\Controllers\Posts\PostPreviewController;
use App\Http\Controllers\Profile\PasswordController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\ProfilePhotoController;
use App\Http\Controllers\Profile\SlugController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserBioController;
use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('search', SearchController::class)->name('search');
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

// Registered last so fixed addresses such as /posts/create are matched first.
Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
