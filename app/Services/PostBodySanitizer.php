<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans HTML from the Quill editor before it is saved. Anything not on the allow-list
 * (scripts, event handlers, styles, iframes, unknown tags) is removed.
 */
class PostBodySanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $blocks = ['p', 'h2', 'h3', 'h4', 'blockquote', 'pre', 'li', 'ol', 'ul'];

        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('sub')
            ->allowElement('sup')
            ->allowElement('blockquote')
            ->allowElement('pre')
            ->allowElement('code')
            ->allowElement('ol')
            ->allowElement('ul')
            ->allowElement('li', ['data-list'])
            ->allowElement('a', ['href'])
            ->allowElement('img', ['src', 'alt', 'width', 'height'])
            // Quill alignment and indent classes, e.g. "ql-align-center", "ql-indent-1".
            ->allowAttribute('class', $blocks)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http'])
            ->allowMediaHosts([parse_url((string) config('app.url'), PHP_URL_HOST)])
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow ugc')
            ->withMaxInputLength(500_000);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(?string $html): string
    {
        // Non-breaking spaces from the editor would stop lines from wrapping; store normal spaces instead.
        $html = str_replace(['&nbsp;', '&#160;', "\u{00A0}"], ' ', (string) $html);

        return trim(str_replace("\u{00A0}", ' ', $this->sanitizer->sanitize($html)));
    }

    /**
     * True when the cleaned HTML has readable text or an image, i.e. it is not just "<p><br></p>".
     */
    public function hasContent(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\xC2\xA0") !== ''
            || str_contains($html, '<img');
    }
}
