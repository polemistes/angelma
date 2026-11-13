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
 *
 * Text boxes may also hold HTML (PLAN.md section 6.3). For them the HTML is let through
 * and the whole result goes through the Html filter, which applies the same rules.
 */
final class Markdown
{
    /** @var array<int, MarkdownConverter> the converter that strips HTML (0) and the one that lets it through (1) */
    private static array $converters = [];

    /**
     * @param bool $allowHtml keep the harmless part of HTML written in the text, for text
     *                        boxes only; without PHP's dom extension it is stripped anyway
     */
    public static function render(string $markdown, bool $allowHtml = false): string
    {
        if (trim($markdown) === '') {
            return '';
        }
        if ($allowHtml && Html::available()) {
            return Html::clean((string) self::converter(true)->convert($markdown));
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

    private static function converter(bool $allowHtml = false): MarkdownConverter
    {
        if (!isset(self::$converters[(int) $allowHtml])) {
            $environment = new Environment([
                'html_input' => $allowHtml ? 'allow' : 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 50,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            if (!$allowHtml) {
                // With HTML allowed, the Html filter makes these adjustments for the whole result.
                $environment->addEventListener(DocumentParsedEvent::class, [self::class, 'adjust']);
            }
            self::$converters[(int) $allowHtml] = new MarkdownConverter($environment);
        }
        return self::$converters[(int) $allowHtml];
    }

    /** @internal Event listener: clamp heading levels, fix site links, mark external links. */
    public static function adjust(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Heading) {
                $node->setLevel(max(2, min(4, $node->getLevel())));
            } elseif ($node instanceof Link || $node instanceof Image) {
                $url = $node->getUrl();
                $node->setUrl(Html::siteUrl($url));
                if ($node instanceof Link && Html::isExternal($url)) {
                    $node->data->set('attributes/rel', 'noopener');
                }
            }
        }
    }
}
