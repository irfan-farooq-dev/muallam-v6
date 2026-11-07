<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Compares two versions of a post as plain text: first paragraph by paragraph, then word by word inside
 * the paragraphs that changed. Formatting (bold, links) is ignored; images show as "[Image: description]".
 */
class PostDiff
{
    /**
     * Above this many comparisons a changed paragraph is shown as removed + added instead of word by word.
     */
    private const MAX_WORD_CELLS = 250_000;

    private const BLOCK_TAGS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre'];

    /**
     * @return list<array{tag: string, status: 'same'|'changed'|'added'|'removed', segments: list<array{type: 'same'|'added'|'removed', text: string}>}>
     */
    public function body(string $oldHtml, string $newHtml): array
    {
        $old = $this->blocks($oldHtml);
        $new = $this->blocks($newHtml);
        $operations = $this->longestCommonSubsequence(
            array_map(fn (array $block): string => $block['tag'].'|'.$block['text'], $old),
            array_map(fn (array $block): string => $block['tag'].'|'.$block['text'], $new),
        );

        $result = [];
        $removed = [];
        $added = [];

        $flush = function () use (&$result, &$removed, &$added): void {
            foreach (array_keys($removed + $added) as $index) {
                $before = $removed[$index] ?? null;
                $after = $added[$index] ?? null;

                if (! $before || ! $after) {
                    continue;
                }

                $segments = $this->words($before['text'], $after['text']);
                $sharesWords = collect($segments)->contains(fn (array $segment): bool => $segment['type'] === 'same' && trim($segment['text']) !== '');

                if ($sharesWords) {
                    $result[] = ['tag' => $after['tag'], 'status' => 'changed', 'segments' => $segments];
                } else {
                    $result[] = ['tag' => $before['tag'], 'status' => 'removed', 'segments' => [['type' => 'removed', 'text' => $before['text']]]];
                    $result[] = ['tag' => $after['tag'], 'status' => 'added', 'segments' => [['type' => 'added', 'text' => $after['text']]]];
                }
            }
            foreach (array_slice($removed, count($added)) as $block) {
                $result[] = ['tag' => $block['tag'], 'status' => 'removed', 'segments' => [['type' => 'removed', 'text' => $block['text']]]];
            }
            foreach (array_slice($added, count($removed)) as $block) {
                $result[] = ['tag' => $block['tag'], 'status' => 'added', 'segments' => [['type' => 'added', 'text' => $block['text']]]];
            }
            $removed = [];
            $added = [];
        };

        foreach ($operations as [$type, $oldIndex, $newIndex]) {
            if ($type === 'same') {
                $flush();
                $result[] = ['tag' => $new[$newIndex]['tag'], 'status' => 'same', 'segments' => [['type' => 'same', 'text' => $new[$newIndex]['text']]]];
            } elseif ($type === 'removed') {
                $removed[] = $old[$oldIndex];
            } else {
                $added[] = $new[$newIndex];
            }
        }
        $flush();

        return $result;
    }

    /**
     * Word-by-word changes between two plain texts.
     *
     * @return list<array{type: 'same'|'added'|'removed', text: string}>
     */
    public function words(string $old, string $new): array
    {
        if ($old === $new) {
            return $old === '' ? [] : [['type' => 'same', 'text' => $old]];
        }

        $oldWords = $this->tokens($old);
        $newWords = $this->tokens($new);

        if (count($oldWords) * count($newWords) > self::MAX_WORD_CELLS) {
            return array_values(array_filter([
                $old !== '' ? ['type' => 'removed', 'text' => $old] : null,
                $new !== '' ? ['type' => 'added', 'text' => $new] : null,
            ]));
        }

        $segments = [];
        foreach ($this->longestCommonSubsequence($oldWords, $newWords) as [$type, $oldIndex, $newIndex]) {
            $text = $type === 'added' ? $newWords[$newIndex] : $oldWords[$oldIndex];
            $last = array_key_last($segments);

            if ($last !== null && $segments[$last]['type'] === $type) {
                $segments[$last]['text'] .= $text;
            } else {
                $segments[] = ['type' => $type, 'text' => $text];
            }
        }

        return $segments;
    }

    /**
     * The post body as a list of plain-text paragraphs, headings and list items.
     *
     * @return list<array{tag: string, text: string}>
     */
    public function blocks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="post-diff-root">'.$html.'</div>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('post-diff-root');
        $blocks = [];

        foreach ($root?->childNodes ?? [] as $node) {
            if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['ul', 'ol'], true)) {
                foreach ($node->childNodes as $item) {
                    if ($item instanceof DOMElement && strtolower($item->tagName) === 'li') {
                        $this->addBlock($blocks, 'li', $item);
                    }
                }

                continue;
            }

            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                $this->addBlock($blocks, in_array($tag, self::BLOCK_TAGS, true) ? $tag : 'p', $node);
            } elseif ($node instanceof DOMText) {
                $this->addBlock($blocks, 'p', $node);
            }
        }

        return $blocks;
    }

    /**
     * @param  list<array{tag: string, text: string}>  $blocks
     */
    private function addBlock(array &$blocks, string $tag, DOMNode $node): void
    {
        $text = $this->plainText($node);

        if ($text !== '') {
            $blocks[] = ['tag' => $tag, 'text' => $text];
        }
    }

    private function plainText(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes ?? [] as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->textContent;
            } elseif ($child instanceof DOMElement && strtolower($child->tagName) === 'img') {
                $description = trim($child->getAttribute('alt'));
                $text .= ' [Image'.($description !== '' ? ': '.$description : '').'] ';
            } elseif ($child instanceof DOMElement && strtolower($child->tagName) === 'br') {
                $text .= ' ';
            } else {
                $text .= $this->plainText($child);
            }
        }

        if ($node instanceof DOMText) {
            $text = $node->textContent;
        }

        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }

    /**
     * Words with the spaces after them kept as separate tokens, so the text joins back exactly.
     *
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        return preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Edit steps turning $old into $new, keeping as much as possible unchanged.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{0: 'same'|'removed'|'added', 1: ?int, 2: ?int}>
     */
    private function longestCommonSubsequence(array $old, array $new): array
    {
        $oldCount = count($old);
        $newCount = count($new);

        $start = 0;
        while ($start < $oldCount && $start < $newCount && $old[$start] === $new[$start]) {
            $start++;
        }

        $endOld = $oldCount;
        $endNew = $newCount;
        while ($endOld > $start && $endNew > $start && $old[$endOld - 1] === $new[$endNew - 1]) {
            $endOld--;
            $endNew--;
        }

        $rows = $endOld - $start;
        $columns = $endNew - $start;
        $lengths = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));

        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $columns - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $old[$start + $i] === $new[$start + $j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $steps = [];
        for ($k = 0; $k < $start; $k++) {
            $steps[] = ['same', $k, $k];
        }

        $i = 0;
        $j = 0;
        while ($i < $rows || $j < $columns) {
            if ($i < $rows && $j < $columns && $old[$start + $i] === $new[$start + $j]) {
                $steps[] = ['same', $start + $i++, $start + $j++];
            } elseif ($i < $rows && ($j === $columns || $lengths[$i + 1][$j] >= $lengths[$i][$j + 1])) {
                $steps[] = ['removed', $start + $i++, null];
            } else {
                $steps[] = ['added', null, $start + $j++];
            }
        }

        for ($k = 0; $k < $oldCount - $endOld; $k++) {
            $steps[] = ['same', $endOld + $k, $endNew + $k];
        }

        return $steps;
    }
}
