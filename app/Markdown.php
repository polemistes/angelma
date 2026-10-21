<?php

declare(strict_types=1);

namespace Angelma;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Markdown to HTML. Raw HTML is stripped, unsafe links are removed, headings are kept
 * between h2 and h4 (h1 belongs to the page), and links to other sites get
 * rel="noopener". Links and images starting with / point into the site, so they get
 * the subfolder in front when the site lives in one (write [About](/about)).
 */
final class Markdown
{
    private static ?MarkdownConverter $converter = null;

    public static function render(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }
        return (string) self::converter()->convert($markdown);
    }

    /** The first paragraph of a Markdown text as plain text, for excerpts. */
    public static function firstParagraph(string $markdown, int $maxLength = 300): string
    {
        $document = (new MarkdownParser(self::converter()->getEnvironment()))->parse($markdown);
        foreach ($document->iterator() as $node) {
            if ($node instanceof Paragraph) {
                $text = '';
                foreach ($node->iterator() as $inline) {
                    if ($inline instanceof Newline) {
                        $text .= ' ';
                    } elseif ($inline instanceof StringContainerInterface) {
                        $text .= $inline->getLiteral();
                    }
                }
                $text = trim((string) preg_replace('/\s+/u', ' ', $text));
                if ($text === '') {
                    continue;
                }
                if (mb_strlen($text) > $maxLength) {
                    $text = rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
                }
                return $text;
            }
        }
        return '';
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 50,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addEventListener(DocumentParsedEvent::class, [self::class, 'adjust']);
            self::$converter = new MarkdownConverter($environment);
        }
        return self::$converter;
    }

    /** @internal Event listener: clamp heading levels, fix site links, mark external links. */
    public static function adjust(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Heading) {
                $node->setLevel(max(2, min(4, $node->getLevel())));
            } elseif ($node instanceof Link || $node instanceof Image) {
                $url = $node->getUrl();
                if (App::$base !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
                    $node->setUrl(App::$base . $url);
                } elseif ($node instanceof Link && self::isExternal($url)) {
                    $node->data->set('attributes/rel', 'noopener');
                }
            }
        }
    }

    private static function isExternal(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $own = (string) parse_url(App::$request?->origin() ?? '', PHP_URL_HOST);
        return strcasecmp($host, $own) !== 0;
    }
}
