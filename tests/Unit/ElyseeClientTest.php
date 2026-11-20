<?php

namespace Wexample\SymfonyRemoteElysee\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteElysee\Service\ElyseeClient;
use Wexample\SymfonyRemoteElysee\Service\ElyseePageParser;

class ElyseeClientTest extends TestCase
{
    private string $cacheDir;

    /** @var list<string> */
    private array $requested = [];

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/elysee-tests-'.bin2hex(random_bytes(4));
        $this->requested = [];
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);
    }

    /**
     * @param array<string, MockResponse|callable(): MockResponse> $site responses by path
     */
    private function client(array $site): ElyseeClient
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($site): MockResponse {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $this->requested[] = $path;
            $response = $site[$path] ?? new MockResponse('', ['http_code' => 404]);

            return is_callable($response) ? $response() : $response;
        });

        return new ElyseeClient($httpClient, new ElyseePageParser(), new Filesystem(), $this->cacheDir, 'test/1.0', 'https://www.elysee.fr', ['/toutes-les-actualites'], 0, 2, 0);
    }

    private function page(string $title, string ...$links): MockResponse
    {
        return new MockResponse('<html><body><h1>'.$title.'</h1>'.implode('', array_map(static fn (string $link): string => '<a href="'.$link.'">x</a>', $links)).'</body></html>');
    }

    public function testACrawlFollowsTheLinksAndGoesOnWhereItStopped(): void
    {
        $site = [
            '/toutes-les-actualites' => fn () => $this->page('Actualités', '/emmanuel-macron/2026/11/16/a'),
            '/emmanuel-macron/2026/11/16/a' => fn () => $this->page('A', '/emmanuel-macron/2026/11/15/b', '/emmanuel-macron/2026/11/14/gone'),
            '/emmanuel-macron/2026/11/15/b' => fn () => $this->page('B', '/emmanuel-macron/2026/11/16/a'),
        ];

        $this->assertSame(2, $this->client($site)->crawl(2));
        $this->assertSame(1, $this->client($site)->countPending());

        // The page gone is read once, remembered; the others are not asked again.
        $this->requested = [];
        $this->assertSame(1, $this->client($site)->crawl(10));
        $this->assertSame(['/toutes-les-actualites', '/emmanuel-macron/2026/11/14/gone'], $this->requested);
        $this->assertSame(0, $this->client($site)->crawl(10));

        $titles = array_map(static fn ($publication): string => $publication->title, iterator_to_array($this->client($site)->getPublications(), false));
        sort($titles);
        $this->assertSame(['A', 'B'], $titles);
    }

    public function testAFailureKeepsWhatWasRead(): void
    {
        $site = [
            '/toutes-les-actualites' => fn () => $this->page('Actualités', '/emmanuel-macron/2026/11/16/a', '/emmanuel-macron/2026/11/15/down'),
            '/emmanuel-macron/2026/11/16/a' => fn () => $this->page('A'),
            '/emmanuel-macron/2026/11/15/down' => fn () => new MockResponse('', ['http_code' => 503]),
        ];

        try {
            $this->client($site)->crawl(10);
            $this->fail('The crawl should have failed.');
        } catch (RemoteException) {
        }

        $this->assertCount(1, iterator_to_array($this->client($site)->getPublications(), false));
    }
}
