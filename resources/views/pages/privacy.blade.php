@extends('layouts.app')

@section('title', 'Privacy policy')
@section('meta_description', 'How Muallam collects, uses and protects your personal information.')

@php
    $email = config('muallam.contact_email');
    $sections = [
        'who' => 'Who we are',
        'collect' => 'Information we collect',
        'use' => 'How we use it',
        'public' => 'What other people can see',
        'cookies' => 'Cookies and third parties',
        'sharing' => 'When we share information',
        'retention' => 'How long we keep it',
        'rights' => 'Your choices and rights',
        'security' => 'Security',
        'children' => 'Children',
        'changes' => 'Changes to this policy',
        'contact' => 'Contact',
    ];
@endphp

@section('content')
    <section class="section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <x-breadcrumbs class="mb-2" :items="[['Privacy policy']]" />
                    <article class="legal-page">
                        <h2 class="h3 mb-1">Privacy policy</h2>
                        <p class="text-secondary small">Last updated: 9 November 2026</p>

                        <p>This policy explains what personal information {{ config('app.name') }} collects when you read, write or get in touch, why we need it, and the choices you have. We keep it to what the site needs to work.</p>

                        <nav class="legal-toc" aria-label="On this page">
                            <strong>On this page</strong>
                            <ol>
                                @foreach ($sections as $id => $heading)
                                    <li><a href="#{{ $id }}">{{ $heading }}</a></li>
                                @endforeach
                            </ol>
                        </nav>

                        <h3 id="who">1. Who we are</h3>
                        <p>{{ config('app.name') }} (muallam.com) is a platform where people read and publish articles and tutorials. "We" and "us" mean the team that runs the site. You can reach us at <a href="mailto:{{ $email }}">{{ $email }}</a> or through the <a href="{{ route('contact') }}">contact page</a>.</p>

                        <h3 id="collect">2. Information we collect</h3>
                        <p><strong>When you create an account:</strong> your full name, email address and password. Passwords are stored only as a secure one-way hash; we can never see them.</p>
                        <p><strong>When you fill in your profile (optional):</strong> phone number, gender, a short bio, a profile photo and your profile address (for example <code>/users/your-name</code>).</p>
                        <p><strong>When you use the site:</strong> the posts, images, comments and likes you add. If you share a restricted post, we store the email addresses you invite so they can be sent an access link.</p>
                        <p><strong>When you contact us:</strong> your name, email address, the subject and your message, plus the IP address it was sent from (to block abuse).</p>
                        <p><strong>Automatically:</strong> your IP address and browser details are used briefly to keep the site secure, for example to slow down repeated failed sign-ins. We count how many times a post is read, once per visit, without recording who read it.</p>

                        <h3 id="use">3. How we use it</h3>
                        <ul>
                            <li>To run your account, publish your posts and show your comments and likes.</li>
                            <li>To send emails you need: email verification, password resets and restricted-post invitations. We do not send marketing emails.</li>
                            <li>To show you notifications on the site, for example when someone comments on your post.</li>
                            <li>To keep the site safe: preventing spam and abuse, and moderating content that breaks our <a href="{{ route('terms') }}">terms</a>.</li>
                            <li>To answer messages you send us.</li>
                        </ul>

                        <h3 id="public">4. What other people can see</h3>
                        <p>Published posts are public, together with your name, profile photo, author page and the post's like and comment counts. Your comments are public on the posts where you write them. Your bio is shown only if you choose to make it public in your profile.</p>
                        <p>Your email address, phone number and gender are never shown publicly. The list of posts you have liked is visible only to you. Drafts and private posts are visible only to you and to site admins, and restricted posts only to the people you invite.</p>

                        <h3 id="cookies">5. Cookies and third parties</h3>
                        <p>We use only the cookies the site needs to work:</p>
                        <ul>
                            <li>a session cookie that keeps you signed in while you browse,</li>
                            <li>a security cookie that protects forms against forgery,</li>
                            <li>a "remember me" cookie, only if you tick that box when signing in.</li>
                        </ul>
                        <p>We do not use analytics, advertising or tracking cookies. Our fonts are loaded from Google Fonts, so your browser connects to Google's servers to fetch them, as it does on many websites. The share buttons under posts are plain links: nothing is sent to Facebook, WhatsApp, LinkedIn or other networks unless you click one, and then their own privacy policies apply.</p>
                        <p>If we ever add advertising, we will update this policy before it starts.</p>

                        <h3 id="sharing">6. When we share information</h3>
                        <p>We do not sell or rent your personal information. We share it only with the companies that help us run the site, such as our web hosting and email provider, who handle it on our behalf, and when the law requires us to.</p>

                        <h3 id="retention">7. How long we keep it</h3>
                        <ul>
                            <li>Account and profile details: for as long as your account exists.</li>
                            <li>Posts, comments and likes: until you or an admin delete them, or your account is deleted.</li>
                            <li>Read notifications: deleted automatically after 90 days.</li>
                            <li>Images uploaded in the editor but never saved in a post: deleted after 24 hours.</li>
                            <li>Contact messages: kept while we deal with them, then deleted.</li>
                        </ul>
                        <p>When an account is deleted, its posts, comments, likes, images and notifications are deleted with it.</p>

                        <h3 id="rights">8. Your choices and rights</h3>
                        <ul>
                            <li>View and change your details, photo, bio and profile address at any time from your <a href="{{ route('profile.edit') }}">profile</a>.</li>
                            <li>Hide your bio from the public, or delete any of your posts and comments.</li>
                            <li>Ask us for a copy of your personal information, to correct it, or to delete your account, by writing to <a href="mailto:{{ $email }}">{{ $email }}</a> from the email address on your account.</li>
                        </ul>

                        <h3 id="security">9. Security</h3>
                        <p>The site runs over HTTPS, passwords are hashed, and sign-in attempts are rate-limited. Restricted posts need both an invitation and a password. No website can be completely secure, so please use a strong, unique password and tell us straight away if you think your account has been misused.</p>

                        <h3 id="children">10. Children</h3>
                        <p>{{ config('app.name') }} is not meant for children under 13, and they should not create an account. If you believe a child under 13 has given us personal information, contact us and we will delete it.</p>

                        <h3 id="changes">11. Changes to this policy</h3>
                        <p>We may update this policy as the site changes. The date at the top shows the latest version, and we will mention important changes on the site.</p>

                        <h3 id="contact">12. Contact</h3>
                        <p>Questions about privacy? Email <a href="mailto:{{ $email }}">{{ $email }}</a> or use the <a href="{{ route('contact') }}">contact page</a>.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>
@endsection
