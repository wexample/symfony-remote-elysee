<?php

namespace Wexample\SymfonyRemoteElysee\Service;

use Generator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Wexample\PhpRemote\Class\RemoteStatus;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\PhpRemote\Interface\RemoteInterface;
use Wexample\SymfonyRemoteElysee\Class\ElyseePublication;

/**
 * The publications of elysee.fr, under Licence Ouverte — the President's
 * speeches and the Présidence's releases reused freely, crediting « elysee.fr »
 * and linking to the original —, read the way a visitor would: the site lists
 * them only to its own search, its sitemaps are not ours to read.
 *
 * A crawl reads the pages listing the latest publications, then follows the
 * links from one publication to the next and through the dossiers — a visit,
 * a theme — listing them, which reach back through the archive: one request at
 * a time, a pause between two. Each publication read is kept on disk, parsed and compressed,
 * and never asked for again; the addresses still to visit are kept beside, so
 * that the next crawl goes on where this one stopped. A page gone (404) is
 * remembered as such.
 *
 * A request that fails is tried again a few times, then raised as a
 * RemoteException: what was read until then stays on disk.
 */
class ElyseeClient implements RemoteInterface
{
    public const string KEY = 'elysee';

    public const string DEFAULT_BASE_URL = 'https://www.elysee.fr';

    public const array DEFAULT_LISTINGS = ['/toutes-les-actualites', '/agenda', '/plan-du-site'];

    private const string GONE = 'gone';

    private const string DOSSIER = 'dossier';

    /**
     * @param list<string> $listings the pages listing the latest publications, past the base address
     * @param int          $delay    milliseconds between two requests
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ElyseePageParser $parser,
        private readonly Filesystem $filesystem,
        private readonly string $cacheDir,
        private readonly string $userAgent,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly array $listings = self::DEFAULT_LISTINGS,
        private readonly int $delay = 1000,
        private readonly int $attempts = 3,
        private readonly int $retryDelay = 5,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'elysee.fr, publications de la Présidence de la République';
    }

    public function checkStatus(): RemoteStatus
    {
        $count = count($this->readListing($this->listings[0] ?? '/'));

        return 0 === $count
            ? RemoteStatus::down('The latest publications list none: the site changed.')
            : RemoteStatus::up(sprintf('%d publications listed lately.', $count));
    }

    /**
     * Reads at most $limit publications not read yet: the latest first, then
     * those the ones already read link to.
     *
     * @return int how many were read
     */
    public function crawl(int $limit): int
    {
        $latest = [];

        foreach ($this->listings as $listing) {
            foreach ($this->readListing($listing) as $url) {
                $latest[$url] = true;
            }
        }

        // The latest go first, in the order they are listed.
        $frontier = $latest + $this->readFrontier();

        $read = 0;

        try {
            // Breadth first: the links found are visited after those known before.
            while ($read < $limit && [] !== $frontier) {
                $url = array_key_first($frontier);
                unset($frontier[$url]);

                if (is_file($this->pathOf($url))) {
                    continue;
                }

                $links = $this->parser->isPublication($url) ? $this->readPublication($url)?->links : $this->readDossier($url);
                ++$read;

                foreach ($links ?? [] as $link) {
                    if (! isset($frontier[$link]) && ! is_file($this->pathOf($link))) {
                        $frontier[$link] = true;
                    }
                }
            }
        } finally {
            $this->filesystem->dumpFile($this->frontierPath(), json_encode(array_keys($frontier)));
        }

        return $read;
    }

    /**
     * @return int how many addresses are known and not read yet
     */
    public function countPending(): int
    {
        return count($this->readFrontier());
    }

    /**
     * Every publication read, from disk.
     *
     * @return Generator<int, ElyseePublication>
     */
    public function getPublications(): Generator
    {
        $dir = $this->cacheDir.'/publications';

        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $data = json_decode(gzdecode(file_get_contents($file->getPathname())), true);

            if (is_array($data) && ! isset($data['state'])) {
                yield ElyseePublication::fromArray($data);
            }
        }
    }

    /**
     * Fetched, parsed and kept; null for a page gone.
     */
    private function readPublication(string $url): ?ElyseePublication
    {
        $html = $this->fetch($url);

        if (null === $html) {
            $this->filesystem->dumpFile($this->pathOf($url), gzencode(json_encode(['state' => self::GONE])));

            return null;
        }

        $publication = $this->parser->parse($html, $url, $this->baseUrl);
        $this->filesystem->dumpFile($this->pathOf($url), gzencode(json_encode($publication->toArray())));

        return $publication;
    }

    /**
     * A dossier lists publications, and other dossiers: read once, its links
     * followed, nothing kept of it but that it was read.
     *
     * @return list<string>
     */
    private function readDossier(string $url): array
    {
        $html = $this->fetch($url);
        $this->filesystem->dumpFile($this->pathOf($url), gzencode(json_encode(['state' => self::DOSSIER])));

        return null === $html ? [] : $this->parser->parseLinks($html, $this->baseUrl);
    }

    /**
     * @return list<string> the publications and dossiers a listing page links to
     */
    private function readListing(string $listing): array
    {
        return $this->parser->parseLinks((string) $this->fetch($this->baseUrl.$listing), $this->baseUrl);
    }

    /**
     * @return array<string, true>
     */
    private function readFrontier(): array
    {
        $urls = is_file($this->frontierPath()) ? json_decode(file_get_contents($this->frontierPath()), true) : [];

        return array_fill_keys(is_array($urls) ? $urls : [], true);
    }

    private function frontierPath(): string
    {
        return $this->cacheDir.'/frontier.json';
    }

    /**
     * `publications/emmanuel-macron/2026/11/15/slug.json.gz`.
     */
    private function pathOf(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $this->cacheDir.'/publications/'.trim(preg_replace('#[^a-z0-9/_-]#i', '_', $path), '/').'.json.gz';
    }

    /**
     * @return string|null null for a page gone
     */
    private function fetch(string $url): ?string
    {
        for ($attempt = 1; ; ++$attempt) {
            // One request at a time, a pause before each: a visitor's pace.
            usleep($this->delay * 1000);

            try {
                return $this->httpClient->request('GET', $url, [
                    'headers' => ['User-Agent' => $this->userAgent],
                    'timeout' => 60,
                ])->getContent();
            } catch (HttpExceptionInterface $exception) {
                if (in_array($exception->getResponse()->getStatusCode(), [404, 410], true)) {
                    return null;
                }

                $failure = $exception;
            } catch (ExceptionInterface $exception) {
                $failure = $exception;
            }

            if ($attempt >= $this->attempts) {
                throw new RemoteException(self::KEY, sprintf('Unable to read "%s": %s', $url, $failure->getMessage()), $failure);
            }

            sleep($attempt * $this->retryDelay);
        }
    }
}
