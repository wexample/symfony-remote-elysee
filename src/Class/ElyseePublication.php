<?php

namespace Wexample\SymfonyRemoteElysee\Class;

use DateTimeImmutable;

/**
 * A publication of elysee.fr — a release, an article on a visit, the report of
 * the Conseil des ministres —, its own words and the texts it holds in full.
 */
final readonly class ElyseePublication
{
    /**
     * @param string            $url     its address, `https://www.elysee.fr/emmanuel-macron/2026/11/15/…`
     * @param string|null       $section what elysee.fr calls it (« Communiqué de presse », « Article », « Conseil des ministres »)
     * @param list<string>      $tags    its subjects (« International », « Malaisie »)
     * @param string|null       $body    its own words, paragraphs a blank line apart
     * @param list<ElyseeText>  $texts   the texts it holds in full: the speeches delivered on the occasion
     * @param list<string>      $links   the other publications it links to, as addresses
     */
    public function __construct(
        public string $url,
        public string $title,
        public ?DateTimeImmutable $date,
        public ?string $section,
        public array $tags,
        public ?string $body,
        public array $texts,
        public array $links,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'date' => $this->date?->format('Y-m-d'),
            'section' => $this->section,
            'tags' => $this->tags,
            'body' => $this->body,
            'texts' => array_map(static fn (ElyseeText $text): array => $text->toArray(), $this->texts),
            'links' => $this->links,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['url'],
            $data['title'],
            null === $data['date'] ? null : new DateTimeImmutable($data['date']),
            $data['section'],
            $data['tags'],
            $data['body'],
            array_map(ElyseeText::fromArray(...), $data['texts']),
            $data['links'],
        );
    }
}
