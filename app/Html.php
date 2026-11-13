<?php

declare(strict_types=1);

namespace Angelma;

/**
 * The filter for the HTML editors may write in text boxes (PLAN.md section 6.3).
 *
 * The HTML is parsed and then written out again from scratch. Only the elements and
 * attributes listed here are written, and every text and attribute value is escaped,
 * so scripts, event handlers, frames and forms cannot come through whatever the input
 * looks like. An unknown element is left out but the text inside it is kept. Because
 * the output is written from the parsed tree, every element is closed again, and a
 * forgotten or extra end tag cannot break the page around the box.
 */
final class Html
{
    /** Allowed on every element. `style` is cut down to the properties in STYLE. */
    private const GLOBAL_ATTRIBUTES = ['class', 'id', 'title', 'lang', 'dir', 'style'];

    /** The allowed elements, each with the attributes it may have besides the global ones. */
    private const ELEMENTS = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'p' => [], 'br' => [], 'hr' => [], 'div' => [], 'span' => [],
        'h2' => [], 'h3' => [], 'h4' => [],
        'ul' => [], 'ol' => ['start', 'reversed', 'type'], 'li' => ['value'],
        'dl' => [], 'dt' => [], 'dd' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'em' => [], 'strong' => [], 'b' => [], 'i' => [], 'u' => [], 's' => [], 'del' => [], 'ins' => [],
        'mark' => [], 'small' => [], 'sub' => [], 'sup' => [], 'kbd' => [], 'samp' => [], 'var' => [],
        'abbr' => [], 'cite' => [], 'q' => [], 'time' => ['datetime'], 'wbr' => [],
        'figure' => [], 'figcaption' => [], 'details' => ['open'], 'summary' => [],
        'table' => [], 'caption' => [], 'colgroup' => ['span'], 'col' => ['span'],
        'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
    ];

    /** Elements without content or end tag. */
    private const VOID = ['br', 'hr', 'img', 'wbr', 'col'];

    /** Headings stay between h2 and h4, as in Markdown: h1 belongs to the page. */
    private const RENAMED = ['h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4'];

    /** Left out together with everything inside them: what they hold is not text to read. */
    private const REMOVED = [
        'script', 'style', 'template', 'noscript', 'noembed', 'noframes', 'iframe', 'frame', 'frameset',
        'object', 'embed', 'applet', 'audio', 'video', 'canvas', 'svg', 'math', 'title', 'textarea', 'select',
    ];

    /**
     * Elements that are only written directly inside one of these parents; anywhere else
     * only their content is kept. A browser closes an open list item or table cell by
     * itself when the next one starts, and an end tag that no longer matches could then
     * close an element of the page around the box. Keeping these in their place rules
     * that out with either parser.
     */
    private const PARENTS = [
        'li' => ['ul', 'ol'],
        'dt' => ['dl'], 'dd' => ['dl'],
        'caption' => ['table'], 'colgroup' => ['table'], 'col' => ['colgroup'],
        'thead' => ['table'], 'tbody' => ['table'], 'tfoot' => ['table'],
        'tr' => ['table', 'thead', 'tbody', 'tfoot'],
        'td' => ['tr'], 'th' => ['tr'],
        'summary' => ['details'], 'figcaption' => ['figure'],
    ];

    /** The style properties that are kept: text, colours, sizes, spacing, borders, float. */
    private const STYLE = '/^(?:color|background-color|font-(?:size|weight|style|family|variant)|line-height'
        . '|letter-spacing|text-(?:align|decoration|indent|transform)|white-space|vertical-align|list-style-type'
        . '|(?:min-|max-)?(?:width|height)|(?:margin|padding)(?:-(?:top|right|bottom|left))?'
        . '|border(?:-(?:top|right|bottom|left))?(?:-(?:color|style|width))?|border-(?:radius|collapse|spacing)'
        . '|float|clear)$/';

    /** The functions a style value may use. Anything that can load a file, like url(), is not among them. */
    private const STYLE_FUNCTIONS = ['', 'rgb', 'rgba', 'hsl', 'hsla', 'calc', 'var', 'min', 'max', 'clamp'];

    private const XHTML = 'http://www.w3.org/1999/xhtml';

    /** Which parser read the HTML being written out: the one of PHP 8.4 and newer, or the older one. */
    private static bool $modern = true;

    /** False when PHP has no dom extension; HTML is then stripped as in every other text. */
    public static function available(): bool
    {
        return class_exists(\DOMDocument::class);
    }

    /**
     * The harmless part of a piece of HTML.
     *
     * @param bool|null $modernParser only for tests: force the parser of PHP 8.4 and newer
     *                                (true) or the older one (false)
     */
    public static function clean(string $html, ?bool $modernParser = null): string
    {
        if (trim($html) === '') {
            return '';
        }
        self::$modern = $modernParser ?? class_exists(\Dom\HTMLDocument::class);
        return self::children(self::parse($html), '');
    }

    /** A link or image address inside the site (starting with /) gets the site's subfolder in front. */
    public static function siteUrl(string $url): string
    {
        if (App::$base !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return App::$base . $url;
        }
        return $url;
    }

    /** Whether an address leads to another site. */
    public static function isExternal(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $own = (string) parse_url(App::$request?->origin() ?? '', PHP_URL_HOST);
        return strcasecmp($host, $own) !== 0;
    }

    /** The node whose children are the parsed HTML. */
    private static function parse(string $html): object
    {
        if (self::$modern) {
            // PHP 8.4 and newer: the parser browsers use, reading the text as a page
            // does when it stands inside a <div>.
            $root = \Dom\HTMLDocument::createEmpty()->createElement('div');
            $root->innerHTML = $html;
            return $root;
        }
        // PHP 8.2 and 8.3: the older parser. It guesses the character encoding, so every
        // character outside ASCII goes in as a numeric entity, which leaves nothing to guess.
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<html><body>' . mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') . '</body></html>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $document;
    }

    /** @param string $parent the nearest element around these nodes that is written, '' at the top */
    private static function children(object $node, string $parent): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $html .= self::element($child, $parent);
            } elseif ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $html .= htmlspecialchars((string) $child->nodeValue, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            }
            // Comments, doctypes and processing instructions are left out.
        }
        return $html;
    }

    private static function element(object $node, string $parent): string
    {
        $name = strtolower((string) $node->localName);
        $name = self::RENAMED[$name] ?? $name;
        if (in_array($name, self::REMOVED, true) || ($node->namespaceURI ?? self::XHTML) !== self::XHTML) {
            return '';
        }
        $written = isset(self::ELEMENTS[$name])
            && (!isset(self::PARENTS[$name]) || in_array($parent, self::PARENTS[$name], true));
        if (!$written) {
            return self::children($node, $parent);
        }
        $attributes = self::attributes($node, $name);
        if ($name === 'img' && !isset($attributes['src'])) {
            return '';
        }
        $tag = '<' . $name;
        foreach ($attributes as $key => $value) {
            $tag .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
        }
        if (in_array($name, self::VOID, true)) {
            return $tag . '>';
        }
        $inner = self::children($node, $name);
        if ($name === 'pre' && self::$modern && str_starts_with($inner, "\n")) {
            // A browser drops a line break right after <pre>, and so did the newer parser;
            // the older one keeps it.
            $inner = "\n" . $inner;
        }
        return $tag . '>' . $inner . '</' . $name . '>';
    }

    /**
     * The attributes of an element that are kept, checked and in the order they were written.
     *
     * @return array<string, string>
     */
    private static function attributes(object $node, string $name): array
    {
        $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::ELEMENTS[$name]);
        $kept = [];
        foreach ($node->attributes as $attribute) {
            $key = strtolower((string) $attribute->name);
            if (!in_array($key, $allowed, true) || isset($kept[$key])) {
                continue;
            }
            $value = (string) $attribute->value;
            $value = match ($key) {
                'href' => self::address($value, ['http', 'https', 'mailto', 'tel']),
                'src' => self::address($value, ['http', 'https'], true),
                'style' => self::style($value),
                'target' => in_array(strtolower($value), ['_blank', '_self', '_parent', '_top'], true) ? strtolower($value) : null,
                'rel' => implode(' ', preg_grep('/^[a-z-]+$/', preg_split('/\s+/', strtolower($value)) ?: []) ?: []),
                default => $value,
            };
            if ($value !== null && !($value === '' && in_array($key, ['style', 'rel'], true))) {
                $kept[$key] = $value;
            }
        }
        if ($name === 'a' && isset($kept['href']) && self::isExternal($kept['href'])) {
            // Links to other sites get rel="noopener", as in Markdown.
            $rel = isset($kept['rel']) ? explode(' ', $kept['rel']) : [];
            if (!in_array('noopener', $rel, true)) {
                $rel[] = 'noopener';
            }
            $kept['rel'] = implode(' ', $rel);
        }
        return $kept;
    }

    /**
     * A link or image address when it is safe to use, else null. Addresses without a
     * scheme stay on the site and are fine; with a scheme, it must be one of the allowed
     * ones, which leaves out javascript: and its relatives however they are disguised.
     *
     * @param list<string> $schemes
     */
    private static function address(string $url, array $schemes, bool $image = false): ?string
    {
        $url = trim($url);
        // Browsers ignore control characters and line breaks in an address, so the check does too.
        $bare = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $bare, $match)) {
            $ok = in_array(strtolower($match[1]), $schemes, true)
                || ($image && preg_match('#^data:image/(?:png|gif|jpeg|webp)[;,]#i', $bare) === 1);
            if (!$ok) {
                return null;
            }
        }
        return self::siteUrl($url);
    }

    /** The declarations of a style attribute that use an allowed property and a plain value. */
    private static function style(string $css): string
    {
        $kept = [];
        foreach (explode(';', $css) as $declaration) {
            $parts = explode(':', $declaration, 2);
            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1] ?? '');
            if (preg_match(self::STYLE, $property) !== 1 || preg_match('/^[a-z0-9 #%.,()+\-\/!]+$/i', $value) !== 1) {
                continue;
            }
            preg_match_all('/([a-z-]*)\s*\(/i', $value, $functions);
            if (array_diff(array_map('strtolower', $functions[1]), self::STYLE_FUNCTIONS) !== []) {
                continue;
            }
            $kept[] = $property . ': ' . $value;
        }
        return implode('; ', $kept);
    }
}
