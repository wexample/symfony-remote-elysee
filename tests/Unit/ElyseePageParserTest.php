<?php

namespace Wexample\SymfonyRemoteElysee\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteElysee\Service\ElyseePageParser;

class ElyseePageParserTest extends TestCase
{
    private const string URL = 'https://www.elysee.fr/emmanuel-macron/2026/11/16/visite-cambodge';

    public function testAPublicationIsRead(): void
    {
        $publication = (new ElyseePageParser())->parse(file_get_contents(__DIR__.'/../Fixtures/publication.html'), self::URL, 'https://www.elysee.fr');

        $this->assertSame("Visite d'État au Cambodge et Sommet de la Francophonie.", $publication->title);
        $this->assertSame('Article', $publication->section);
        $this->assertSame('2026-11-16', $publication->date->format('Y-m-d'));
        $this->assertSame(['International', 'Cambodge'], $publication->tags);
        $this->assertSame("Le Président de la République s'est rendu au Cambodge.\n\nRevoir le discours :", $publication->body);
    }

    public function testItsTextsAreReadOnceEach(): void
    {
        $publication = (new ElyseePageParser())->parse(file_get_contents(__DIR__.'/../Fixtures/publication.html'), self::URL, 'https://www.elysee.fr');

        $this->assertCount(1, $publication->texts);
        $this->assertSame('Discours du Président de la République au Sommet de la francophonie.', $publication->texts[0]->title);
        $this->assertSame('2026-11-15', $publication->texts[0]->date->format('Y-m-d'));
        $this->assertSame("Majesté,\nMesdames, Messieurs,\n\nC'est avec une grande joie…\n\nla langue ;\n\nla jeunesse.", $publication->texts[0]->content);
    }

    public function testOnlyThePublicationsLinkedAreKept(): void
    {
        $publication = (new ElyseePageParser())->parse(file_get_contents(__DIR__.'/../Fixtures/publication.html'), self::URL, 'https://www.elysee.fr');

        $this->assertSame([
            'https://www.elysee.fr/emmanuel-macron/2026/11/15/discours-francophonie',
            'https://www.elysee.fr/emmanuel-macron/2026/11/14/arrivee-a-phnom-penh',
        ], $publication->links);
    }

    public function testAPageWithoutTitleIsNoPublication(): void
    {
        $this->expectException(RemoteException::class);

        (new ElyseePageParser())->parse('<html><body><p>Maintenance</p></body></html>', self::URL, 'https://www.elysee.fr');
    }
}
