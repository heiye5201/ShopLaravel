<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class ProductContentSanitizer
{
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $body = $document->getElementsByTagName('body')->item(0);
            if ($body === null) {
                return null;
            }

            $result = $this->children($body);
            $text = str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($result), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return trim($text) === '' && ! str_contains($result, '<img ') ? null : $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function children(DOMNode $parent): string
    {
        $result = '';
        foreach ($parent->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $result .= htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                continue;
            }
            if (! $node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form', 'input', 'button', 'textarea', 'select'], true)) {
                continue;
            }
            $content = $this->children($node);
            if (! in_array($tag, ['p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'span', 'hr', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'], true)) {
                $result .= $content;

                continue;
            }
            $attributes = '';
            if (in_array($tag, ['a', 'img'], true)) {
                $attribute = $tag === 'a' ? 'href' : 'src';
                $url = trim($node->getAttribute($attribute));
                if (! preg_match('~^https?://[^\s]+$~i', $url) && ! ($tag === 'img' && preg_match('~^/storage/products/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp|gif)$~i', $url))) {
                    $result .= $content;

                    continue;
                }
                $attributes = ' '.$attribute.'="'.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
                $attributes .= $tag === 'a' ? ' rel="noopener noreferrer"' : ' alt="'.htmlspecialchars($node->getAttribute('alt'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" loading="lazy"';
            }
            $styles = [];
            foreach (explode(';', $node->getAttribute('style')) as $declaration) {
                $parts = explode(':', $declaration, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                [$property, $value] = array_map('trim', $parts);
                $property = strtolower($property);
                $patterns = [
                    'font-weight' => '/^(normal|bold|[1-9]00)$/i',
                    'font-style' => '/^(normal|italic|oblique)$/i',
                    'text-decoration' => '/^(none|underline|line-through)$/i',
                    'text-align' => '/^(left|right|center|justify)$/i',
                    'font-size' => '/^([1-9][0-9]?)(px|pt)$/i',
                    'color' => '/^(#[a-f0-9]{3,8}|[a-z]+|rgba?\([0-9.,% ]+\))$/i',
                    'background-color' => '/^(#[a-f0-9]{3,8}|[a-z]+|rgba?\([0-9.,% ]+\))$/i',
                    'width' => '/^([0-9]{1,4})(px|%)$/i',
                    'height' => '/^([0-9]{1,4})(px|%)$/i',
                ];
                if (isset($patterns[$property]) && preg_match($patterns[$property], $value)) {
                    $styles[] = $property.': '.$value;
                }
            }
            if ($styles !== []) {
                $attributes .= ' style="'.htmlspecialchars(implode('; ', $styles), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
            }
            $result .= '<'.$tag.$attributes.'>'.(in_array($tag, ['br', 'img', 'hr'], true) ? '' : $content.'</'.$tag.'>');
        }

        return $result;
    }
}
