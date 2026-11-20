<?php

namespace Wexample\SymfonyRemoteElysee\Class;

use DateTimeImmutable;

/**
 * A text a publication of elysee.fr holds in full — a speech, as delivered or
 * as written to be (« Seul le prononcé fait foi »), an address, a letter.
 */
final readonly class ElyseeText
{
    /**
     * @param string $content its paragraphs, a blank line between two
     */
    public function __construct(
        public string $title,
        public ?DateTimeImmutable $date,
        public string $content,
    ) {
    }

    /**
     * @return array{title: string, date: ?string, content: string}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'date' => $this->date?->format('Y-m-d'), 'content' => $this->content];
    }

    /**
     * @param array{title: string, date: ?string, content: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['title'], null === $data['date'] ? null : new DateTimeImmutable($data['date']), $data['content']);
    }
}
