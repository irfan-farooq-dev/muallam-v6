@extends('layouts.app')

@section('title', 'Terms & conditions')
@section('meta_description', 'The rules for using Muallam: accounts, your content, acceptable use and moderation.')

@php
    $email = config('muallam.contact_email');
    $sections = [
        'agreement' => 'Agreeing to these terms',
        'accounts' => 'Your account',
        'content' => 'Your content',
        'rules' => 'Content rules',
        'moderation' => 'Moderation',
        'restricted' => 'Private and restricted posts',
        'site' => 'Our site and brand',
        'disclaimer' => 'Disclaimer',
        'liability' => 'Limitation of liability',
        'ending' => 'Ending your use',
        'changes' => 'Changes to these terms',
        'contact' => 'Contact',
    ];
@endphp

@section('content')
    <section class="section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <x-breadcrumbs class="mb-2" :items="[['Terms & conditions']]" />
                    <article class="legal-page">
                        <h2 class="h3 mb-1">Terms &amp; conditions</h2>
                        <p class="text-secondary small">Last updated: 9 November 2026</p>

                        <p>These terms are the rules for using {{ config('app.name') }} (muallam.com). They are written to be read: please take a few minutes, especially if you plan to write.</p>

                        <nav class="legal-toc" aria-label="On this page">
                            <strong>On this page</strong>
                            <ol>
                                @foreach ($sections as $id => $heading)
                                    <li><a href="#{{ $id }}">{{ $heading }}</a></li>
                                @endforeach
                            </ol>
                        </nav>

                        <h3 id="agreement">1. Agreeing to these terms</h3>
                        <p>By using the site or creating an account, you agree to these terms and to our <a href="{{ route('privacy') }}">privacy policy</a>. If you do not agree, please do not use the site. You must be at least 13 years old to create an account.</p>

                        <h3 id="accounts">2. Your account</h3>
                        <ul>
                            <li>Use your real name or a name you are known by, and an email address you can access. You need to verify your email before you can publish.</li>
                            <li>One account per person. Do not share your account or pretend to be someone else.</li>
                            <li>Keep your password secret. You are responsible for what happens under your account, so tell us straight away if you think someone else has used it.</li>
                        </ul>

                        <h3 id="content">3. Your content</h3>
                        <p>You own what you write. By publishing on {{ config('app.name') }}, you give us a free, non-exclusive, worldwide permission to store, display and distribute your content on the site and in previews (for example when a post is shared on social media), for as long as it stays on the site. You can delete your posts and comments at any time.</p>
                        <p>You are responsible for what you post. Only publish text and images that you created or have the right to use, and credit your sources.</p>

                        <h3 id="rules">4. Content rules</h3>
                        <p>Do not post or send anything that:</p>
                        <ul>
                            <li>is illegal, or encourages violence or illegal activity;</li>
                            <li>attacks or harasses people, or is hateful towards any group, including on the basis of religion, ethnicity, gender or disability;</li>
                            <li>is sexually explicit, or exploits or endangers children in any way;</li>
                            <li>copies someone else's work without permission (plagiarism or copyright infringement);</li>
                            <li>shares other people's personal information without their consent;</li>
                            <li>is spam, advertising you were not invited to post, or misleading links;</li>
                            <li>is deliberately false in a way that could harm people, such as dangerous health claims;</li>
                            <li>contains viruses or tries to break, overload or scrape the site.</li>
                        </ul>
                        <p>Disagreement is welcome; keep comments respectful and on topic.</p>

                        <h3 id="moderation">5. Moderation</h3>
                        <p>To keep the site useful and safe, admins may, without warning when needed:</p>
                        <ul>
                            <li>edit a post, for example to fix formatting or remove content that breaks these rules. The author sees exactly which words were changed;</li>
                            <li>unpublish a post (it goes back to being a draft) or delete it;</li>
                            <li>hide or delete comments;</li>
                            <li>deactivate or delete accounts that break these terms.</li>
                        </ul>
                        <p>You are told on the site when an admin changes your post or comment. If you think a decision was wrong, <a href="{{ route('contact') }}">contact us</a>. Post authors can also hide comments on their own posts.</p>

                        <h3 id="restricted">6. Private and restricted posts</h3>
                        <p>Private posts are visible only to you (and to admins for moderation). Restricted posts are shared with the people you invite, who also need the password you set. You decide whom to invite. If you receive an invitation, do not pass the link or password on to others.</p>

                        <h3 id="site">7. Our site and brand</h3>
                        <p>The {{ config('app.name') }} name, logo and site design belong to us. Apart from the content that members own, do not copy or reuse the site without permission.</p>

                        <h3 id="disclaimer">8. Disclaimer</h3>
                        <p>Posts are written by members and show their own views, not ours. Articles are for learning and general information, not professional advice (medical, legal, financial or religious rulings); check important information with a qualified person. We try to keep the site running smoothly, but it is provided "as is", and we cannot promise it will always be available or error-free.</p>

                        <h3 id="liability">9. Limitation of liability</h3>
                        <p>As far as the law allows, {{ config('app.name') }} is not responsible for losses that come from using the site, from relying on content written by members, or from the site being unavailable. Nothing in these terms limits rights you have that cannot legally be limited.</p>

                        <h3 id="ending">10. Ending your use</h3>
                        <p>You can stop using the site at any time, and ask us to delete your account by writing to <a href="mailto:{{ $email }}">{{ $email }}</a>. We may suspend or close accounts that break these terms.</p>

                        <h3 id="changes">11. Changes to these terms</h3>
                        <p>We may update these terms as the site grows. The date at the top shows the latest version. If you keep using the site after a change, you accept the new terms.</p>

                        <h3 id="contact">12. Contact</h3>
                        <p>Questions about these terms? Email <a href="mailto:{{ $email }}">{{ $email }}</a> or use the <a href="{{ route('contact') }}">contact page</a>.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>
@endsection
