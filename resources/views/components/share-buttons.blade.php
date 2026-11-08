{{--
    "Share this post" row: brand buttons that open each network's own share page, plus copy-link and, on phones,
    the device's share sheet. Link previews come from the page's Open Graph tags.
    Usage: <x-share-buttons :url="route('posts.show', $post)" :title="$post->title" />
--}}
@props(['url', 'title'])

@php
    $encodedUrl = rawurlencode($url);
    $encodedTitle = rawurlencode($title);
    $networks = [
        ['name' => 'Facebook', 'icon' => 'bi-facebook', 'class' => 'share-facebook',
            'href' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}"],
        ['name' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'class' => 'share-whatsapp',
            'href' => 'https://wa.me/?text='.rawurlencode("{$title} {$url}")],
        ['name' => 'LinkedIn', 'icon' => 'bi-linkedin', 'class' => 'share-linkedin',
            'href' => "https://www.linkedin.com/sharing/share-offsite/?url={$encodedUrl}"],
        ['name' => 'X', 'icon' => 'bi-twitter-x', 'class' => 'share-x',
            'href' => "https://x.com/intent/post?url={$encodedUrl}&text={$encodedTitle}"],
        ['name' => 'Telegram', 'icon' => 'bi-telegram', 'class' => 'share-telegram',
            'href' => "https://t.me/share/url?url={$encodedUrl}&text={$encodedTitle}"],
        ['name' => 'Email', 'icon' => 'bi-envelope-fill', 'class' => 'share-email',
            'href' => "mailto:?subject={$encodedTitle}&body=".rawurlencode("{$title}\n\n{$url}")],
    ];
@endphp

<div {{ $attributes->class(['share-buttons d-flex flex-wrap align-items-center gap-2']) }}
     x-data="shareButtons(@js($url))">
    <span class="share-label me-1">Share</span>

    @foreach ($networks as $network)
        <a href="{{ $network['href'] }}" class="share-button {{ $network['class'] }}"
           @if ($network['name'] !== 'Email') target="_blank" rel="noopener noreferrer" @endif
           title="Share on {{ $network['name'] }}" aria-label="Share on {{ $network['name'] }}">
            <i class="bi {{ $network['icon'] }}" aria-hidden="true"></i>
        </a>
    @endforeach

    <button type="button" class="share-button share-copy" :class="{ 'is-copied': copied }"
            title="Copy link" aria-label="Copy link"
            @click="copy()">
        <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-link-45deg'" aria-hidden="true"></i>
    </button>
    <span class="share-copied small" x-show="copied" x-cloak role="status">Link copied</span>

    {{-- Phones and some browsers have a built-in share sheet (Messenger, Instagram, SMS, ...). --}}
    <button type="button" class="share-button share-native" x-show="canShare" x-cloak
            title="More ways to share" aria-label="More ways to share"
            @click="navigator.share({ title: @js($title), url: @js($url) }).catch(() => {})">
        <i class="bi bi-share-fill" aria-hidden="true"></i>
    </button>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('shareButtons', (url) => ({
                    copied: false,
                    canShare: typeof navigator.share === 'function',

                    async copy() {
                        try {
                            await navigator.clipboard.writeText(url);
                        } catch (error) {
                            // The clipboard API needs HTTPS (and may be blocked); fall back to the older copy command.
                            const field = Object.assign(document.createElement('textarea'), { value: url });
                            field.setAttribute('readonly', '');
                            field.style.position = 'fixed';
                            field.style.opacity = '0';
                            document.body.appendChild(field);
                            field.select();
                            const worked = document.execCommand('copy');
                            field.remove();

                            if (!worked) {
                                window.prompt('Copy this link:', url);
                                return;
                            }
                        }

                        this.copied = true;
                        setTimeout(() => (this.copied = false), 2000);
                    },
                }));
            });
        </script>
    @endpush
@endonce
