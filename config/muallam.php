<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reserved user slugs
    |--------------------------------------------------------------------------
    |
    | Profile URLs look like /users/{slug}/bio. These words can never be used
    | as a slug, either because they clash with routes or would mislead readers.
    |
    */

    'slug_change_cooldown_days' => 30,

    /*
    | New posts start in this category (it is created by CategorySeeder).
    */
    'default_category_slug' => 'general',

    /*
    | Shown on the contact, privacy and terms pages (same address as the old muallam.com).
    */
    'contact_email' => env('MUALLAM_CONTACT_EMAIL', 'user.muallam@gmail.com'),

    'reserved_slugs' => [
        'admin', 'administrator', 'api', 'auth', 'bio', 'blog', 'blogs', 'categories',
        'category', 'contact', 'dashboard', 'edit', 'email', 'help', 'home', 'login',
        'logout', 'me', 'moderator', 'muallam', 'new', 'password', 'post', 'posts',
        'profile', 'register', 'root', 'search', 'settings', 'signin', 'signup',
        'staff', 'support', 'system', 'user', 'users', 'verify', 'www',
    ],

];
