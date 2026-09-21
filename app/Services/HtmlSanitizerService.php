<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizerService
{
    /**
     * Allowed HTML tag names.
     *
     * @var array<string, bool>
     */
    protected const ALLOWED_TAGS = [
        'p' => true,
        'br' => true,
        'hr' => true,
        'h1' => true,
        'h2' => true,
        'h3' => true,
        'h4' => true,
        'h5' => true,
        'h6' => true,
        'b' => true,
        'strong' => true,
        'i' => true,
        'em' => true,
        'u' => true,
        's' => true,
        'strike' => true,
        'sub' => true,
        'sup' => true,
        'ul' => true,
        'ol' => true,
        'li' => true,
        'blockquote' => true,
        'pre' => true,
        'code' => true,
        'a' => true,
        'img' => true,
        'table' => true,
        'thead' => true,
        'tbody' => true,
        'tfoot' => true,
        'tr' => true,
        'th' => true,
        'td' => true,
        'figure' => true,
        'figcaption' => true,
        'span' => true,
        'div' => true,
    ];

    /**
     * Allowed attributes per tag.
     *
     * @var array<string, array<string, bool>>
     */
    protected const ALLOWED_ATTRIBUTES = [
        'a' => ['href' => true, 'title' => true, 'target' => true, 'rel' => true],
        'img' => ['src' => true, 'alt' => true, 'title' => true, 'width' => true, 'height' => true],
        'th' => ['colspan' => true, 'rowspan' => true, 'align' => true],
        'td' => ['colspan' => true, 'rowspan' => true, 'align' => true],
        'table' => ['border' => true, 'cellpadding' => true, 'cellspacing' => true],
    ];

    /**
     * Sanitize rich HTML string, stripping unsafe tags, script injection, and event handlers.
     */
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $internalErrors = libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        // Load with UTF-8 encoding wrapper
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (! $loaded) {
            return strip_tags($html);
        }

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        if (! $wrapper) {
            return '';
        }

        $this->cleanNode($wrapper);

        // Export inner HTML of the wrapper div
        $output = '';
        foreach ($wrapper->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Recursively clean a DOM node and its descendants.
     */
    protected function cleanNode(DOMNode $node): void
    {
        // Traverse child nodes backwards so removals don't skip indexes
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if (! $child) {
                continue;
            }

            if ($child->nodeType === XML_ELEMENT_NODE) {
                /** @var DOMElement $child */
                $tagName = strtolower($child->nodeName);

                if (! isset(self::ALLOWED_TAGS[$tagName])) {
                    // Tag is not allowed. Check if it's an executable dangerous tag
                    if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg'], true)) {
                        // Completely remove dangerous element and its contents
                        $node->removeChild($child);
                    } else {
                        // Unwrap content: move children to parent before removing tag
                        while ($child->hasChildNodes()) {
                            $childNode = $child->firstChild;
                            $node->insertBefore($childNode, $child);
                        }
                        $node->removeChild($child);
                    }
                    continue;
                }

                // Tag is allowed: clean attributes
                $allowedAttrs = self::ALLOWED_ATTRIBUTES[$tagName] ?? [];

                for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                    $attr = $child->attributes->item($a);
                    if (! $attr) {
                        continue;
                    }

                    $attrName = strtolower($attr->name);
                    $attrValue = trim($attr->value);

                    // Block any event handler (onclick, onload, onerror, etc.)
                    if (str_starts_with($attrName, 'on')) {
                        $child->removeAttributeNode($attr);
                        continue;
                    }

                    // Check whitelist
                    if (! isset($allowedAttrs[$attrName])) {
                        $child->removeAttributeNode($attr);
                        continue;
                    }

                    // Validate URLs for href and src
                    if (in_array($attrName, ['href', 'src'], true)) {
                        if (! $this->isSafeUrl($attrValue)) {
                            $child->removeAttributeNode($attr);
                            continue;
                        }
                    }

                    // For external links with target="_blank", enforce rel="noopener noreferrer"
                    if ($tagName === 'a' && $attrName === 'target' && strtolower($attrValue) === '_blank') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                }

                // Recursively clean children
                $this->cleanNode($child);
            }
        }
    }

    /**
     * Verify if a URL protocol scheme is safe (http, https, mailto, tel, or relative).
     */
    protected function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        // Relative path or anchor
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, './')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false) {
            return true;
        }

        $scheme = strtolower($scheme);

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }
}
