<?php

namespace App\Services;

use DOMDocument;
use DOMNode;
use Illuminate\Validation\ValidationException;

class EmailContent
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'hr', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'mark'];

    public function sanitize(string $html): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $output = '';
            foreach ($document->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $node) {
                $output .= $this->node($node);
            }

            return $output;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($node->nodeType !== XML_ELEMENT_NODE || in_array(strtolower($node->nodeName), ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'textarea', 'button', 'select', 'template', 'noscript'], true)) {
            return '';
        }
        $tag = strtolower($node->nodeName);
        $content = '';
        foreach ($node->childNodes as $child) {
            $content .= $this->node($child);
        }
        if (! in_array($tag, self::TAGS, true)) {
            return $content;
        }
        $attributes = '';
        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            if (preg_match('~^(https?://|mailto:)[^\s\x00-\x1f]+$~iu', $href)) {
                $attributes = ' href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'" rel="noopener noreferrer"';
            }
        }
        if (in_array($tag, ['td', 'th'], true)) {
            foreach (['colspan', 'rowspan'] as $attribute) {
                $value = $node->getAttribute($attribute);
                if (ctype_digit($value) && (int) $value >= 1 && (int) $value <= 20) {
                    $attributes .= ' '.$attribute.'="'.$value.'"';
                }
            }
        }

        return '<'.$tag.$attributes.'>'.(in_array($tag, ['br', 'hr'], true) ? '' : $content.'</'.$tag.'>');
    }

    public function text(string $html): string
    {
        $html = preg_replace_callback('~<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>~si', fn ($m) => $m[2].' ('.$m[1].')', $this->sanitize($html));
        $html = preg_replace('~</(?:p|h[234]|li|tr|blockquote|pre)>|<br>|<hr>~i', "\n", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function prepare(string $body, string $format, string $field = 'body'): string
    {
        $body = $format === 'html' ? $this->sanitize($body) : trim($body);
        $text = $format === 'html' ? $this->text($body) : $body;
        if (trim(str_replace(["\xc2\xa0", "\xe2\x80\x8b"], '', $text)) === '') {
            throw ValidationException::withMessages([$field => 'Write a message before saving or sending.']);
        }

        return $body;
    }
}
