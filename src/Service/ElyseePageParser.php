<?php

namespace Wexample\SymfonyRemoteElysee\Service;

use DateTimeImmutable;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteElysee\Class\ElyseePublication;
use Wexample\SymfonyRemoteElysee\Class\ElyseeText;

/**
 * Reads a publication's page: its title and section from the page's metadata,
 * its own words from its rich-text sections, and the texts it holds in full
 * from its document cards — a title, a day (« 15 novembre 2026 - Seul le
 * prononcé fait foi »), the text —. A video's transcript repeats a card in a
 * dialog: it is not read twice.
 */
class ElyseePageParser
{
    public const array DEFAULT_PREFIXES = ['emmanuel-macron'];

    private const array MONTHS = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12,
    ];

    private const string BLOCKS = 'p, h2, h3, h4, li, blockquote';

    /**
     * @param list<string> $prefixes the presidents' sections of the site (`emmanuel-macron`): their
     *                               publications are `/<prefix>/2026/11/15/<slug>`, their dossiers —
     *                               a visit, a theme, listing its publications — `/<prefix>/<slug>`
     */
    public function __construct(
        private readonly array $prefixes = self::DEFAULT_PREFIXES,
    ) {
    }

    /**
     * Whether $url is a publication's: else a dossier's.
     */
    public function isPublication(string $url): bool
    {
        return 1 === preg_match('#^'.$this->prefixPattern().'/\d{4}/\d{2}/\d{2}/[^/?\#]+$#', (string) parse_url($url, PHP_URL_PATH));
    }

    /**
     * @throws RemoteException when the page has no title: not a publication, or the site changed
     */
    public function parse(
        string $html,
        string $url,
        string $baseUrl
    ): ElyseePublication {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        // A line break is a line of the text.
        foreach (iterator_to_array($document->querySelectorAll('br')) as $break) {
            $break->replaceWith("\n");
        }

        $title = $this->text($document->querySelector('h1'));

        if (null === $title) {
            throw new RemoteException(ElyseeClient::KEY, sprintf('"%s" has no title: the site changed.', $url));
        }

        $published = $this->meta($document, 'og:article:published_time');
        $tags = [];

        foreach ($document->querySelectorAll('meta[property="og:article:tag"]') as $tag) {
            $value = trim((string) $tag->getAttribute('content'));
            $tags[mb_strtolower($value)] ??= $value;
        }

        return new ElyseePublication(
            $url,
            $title,
            null === $published ? $this->dateFromPath($url) : new DateTimeImmutable(substr($published, 0, 10)),
            $this->meta($document, 'og:article:section'),
            array_values(array_filter($tags, static fn (string $tag): bool => '' !== $tag)),
            $this->paragraphs($document->querySelectorAll('section .rich-text .ck-styled')),
            $this->readTexts($document),
            $this->readLinks($document, $baseUrl),
        );
    }

    /**
     * The publications and dossiers a page links to, as addresses.
     *
     * @return list<string>
     */
    public function parseLinks(
        string $html,
        string $baseUrl
    ): array {
        return $this->readLinks(HTMLDocument::createFromString($html, LIBXML_NOERROR), $baseUrl);
    }

    /**
     * @return list<ElyseeText>
     */
    private function readTexts(HTMLDocument $document): array
    {
        $texts = [];

        foreach ($document->querySelectorAll('section.js-document .card') as $card) {
            $title = $this->text($card->querySelector('.card__top__title'));
            $content = $this->paragraphs([$card->querySelector('.card__body')]);

            if (null === $title || null === $content) {
                continue;
            }

            $texts[$title] ??= new ElyseeText($title, $this->dateFromText($this->text($card->querySelector('.chapo'))), $content);
        }

        return array_values($texts);
    }

    /**
     * @return list<string>
     */
    private function readLinks(
        HTMLDocument $document,
        string $baseUrl
    ): array {
        $links = [];
        $origin = rtrim($baseUrl, '/');

        foreach ($document->querySelectorAll('a[href]') as $anchor) {
            $href = (string) $anchor->getAttribute('href');
            $path = str_starts_with($href, $origin) ? substr($href, strlen($origin)) : $href;

            if (1 === preg_match('#^'.$this->prefixPattern().'/(\d{4}/\d{2}/\d{2}/)?[a-z0-9_-]+$#i', $path)) {
                $links[$origin.$path] = true;
            }
        }

        return array_keys($links);
    }

    /**
     * The leaf blocks' texts, a blank line between two.
     *
     * @param iterable<Element|null> $containers
     */
    private function paragraphs(iterable $containers): ?string
    {
        $paragraphs = [];

        foreach ($containers as $container) {
            if (null === $container) {
                continue;
            }

            foreach ($container->querySelectorAll(self::BLOCKS) as $block) {
                // A block holding blocks is read through them.
                if (null !== $block->querySelector(self::BLOCKS)) {
                    continue;
                }

                $text = $this->text($block);

                if (null !== $text) {
                    $paragraphs[] = $text;
                }
            }
        }

        return [] === $paragraphs ? null : implode("\n\n", $paragraphs);
    }

    private function text(?Node $node): ?string
    {
        if (null === $node) {
            return null;
        }

        $lines = array_map(
            static fn (string $line): string => trim(preg_replace('/[^\S\n]+/u', ' ', $line)),
            explode("\n", str_replace("\u{a0}", ' ', (string) $node->textContent))
        );
        $text = trim(implode("\n", array_filter($lines, static fn (string $line): bool => '' !== $line)));

        return '' === $text ? null : $text;
    }

    private function prefixPattern(): string
    {
        return '/(?:'.implode('|', array_map(static fn (string $prefix): string => preg_quote($prefix, '#'), $this->prefixes)).')';
    }

    private function meta(
        HTMLDocument $document,
        string $property
    ): ?string {
        $value = $document->querySelector('meta[property="'.$property.'"]')?->getAttribute('content');

        return null === $value || '' === trim($value) ? null : trim($value);
    }

    /**
     * « 15 novembre 2026 - Seul le prononcé fait foi ».
     */
    private function dateFromText(?string $text): ?DateTimeImmutable
    {
        if (null === $text || 1 !== preg_match('/(\d{1,2})(?:er)?\s+(\p{L}+)\s+(\d{4})/u', $text, $match)) {
            return null;
        }

        $month = self::MONTHS[mb_strtolower($match[2])] ?? null;

        return null === $month ? null : new DateTimeImmutable(sprintf('%04d-%02d-%02d', $match[3], $month, $match[1]));
    }

    private function dateFromPath(string $url): ?DateTimeImmutable
    {
        return 1 === preg_match('#/(\d{4})/(\d{2})/(\d{2})/#', $url, $match)
            ? new DateTimeImmutable($match[1].'-'.$match[2].'-'.$match[3])
            : null;
    }
}
