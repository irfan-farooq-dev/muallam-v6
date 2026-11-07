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

    'reserved_slugs' => [
        'admin', 'administrator', 'api', 'auth', 'bio', 'blog', 'blogs', 'categories',
        'category', 'contact', 'dashboard', 'edit', 'email', 'help', 'home', 'login',
        'logout', 'me', 'moderator', 'muallam', 'new', 'password', 'post', 'posts',
        'profile', 'register', 'root', 'search', 'settings', 'signin', 'signup',
        'staff', 'support', 'system', 'user', 'users', 'verify', 'www',
    ],

];
