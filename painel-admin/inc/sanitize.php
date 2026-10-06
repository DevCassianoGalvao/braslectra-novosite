<?php
declare(strict_types=1);

/**
 * Sanitiza HTML de artigos (lista de permitidos). Remove scripts, estilos inline, handlers de evento,
 * iframes que não sejam YouTube/Vimeo e URLs perigosas.
 */
function sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'h5', 'ul', 'ol', 'li', 'a', 'img', 'blockquote',
        'figure', 'figcaption', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'hr', 'span', 'sup', 'sub', 'code', 'pre', 'iframe',
    ];
    $dropTags = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'noscript', 'svg', 'math', 'base', 'frame', 'frameset', 'applet'];
    $allowedAttrs = [
        'a'      => ['href', 'title', 'target', 'rel'],
        'img'    => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'iframe' => ['src', 'width', 'height', 'title', 'allowfullscreen', 'loading'],
        'td'     => ['colspan', 'rowspan'],
        'th'     => ['colspan', 'rowspan'],
        'h2'     => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'h5' => ['id'], 'p' => ['id'], 'span' => ['id'],
        'ol'     => ['start'],
    ];

    $prev = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="utf-8" ?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $dom->getElementById('__root');
    if (!$root) {
        return '';
    }

    $safeUrl = static function (string $u, bool $allowRelative = true): bool {
        $u = trim(html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $u = preg_replace('/[\x00-\x20]+/', '', $u) ?? '';
        if ($u === '') {
            return false;
        }
        if (preg_match('#^(https?:|mailto:|tel:|//)#i', $u)) {
            return true;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $u)) {
            return false; // javascript:, data:, vbscript: ...
        }
        return $allowRelative;
    };

    $walk = function (DOMNode $node) use (&$walk, $allowedTags, $dropTags, $allowedAttrs, $safeUrl, $dom): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, $dropTags, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!in_array($tag, $allowedTags, true)) {
                // desembrulha: mantém filhos, remove o elemento (div -> conteúdo)
                $walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            if ($tag === 'iframe') {
                $src = $child->getAttribute('src');
                if (!preg_match('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com)/#i', $src)) {
                    $node->removeChild($child);
                    continue;
                }
            }
            $keep = $allowedAttrs[$tag] ?? [];
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $keep, true)) {
                    $child->removeAttribute($attr->name);
                    continue;
                }
                if (($name === 'href' || $name === 'src') && !$safeUrl($attr->value, $tag !== 'iframe')) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a') {
                $target = $child->getAttribute('target');
                if ($target === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $child->removeAttribute('target');
                }
            }
            if ($tag === 'img' && !$child->hasAttribute('loading')) {
                $child->setAttribute('loading', 'lazy');
            }
            $walk($child);
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $dom->saveHTML($c);
    }
    $out = preg_replace('/<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $out) ?? $out;
    return trim($out);
}
