## Crawling

```php
public function __construct(private ElyseeClient $elysee, private RemoteGuard $guard) {}

// The latest publications first, then those already read link to, and the dossiers listing them: at most 500 new pages.
$read = $this->guard->attempt($this->elysee, fn () => $this->elysee->crawl(500), 0);
$this->elysee->countPending();   // addresses known, not read yet
```

A crawl reads the listing pages again each time, so the latest publications come first; the addresses it did not reach are kept, and the next crawl goes on from them. At one request a second, a crawl of a few hundred pages a day reaches back through the archive over the weeks, and keeps up with what is published.

## Reading

```php
foreach ($this->elysee->getPublications() as $publication) {   // from disk, no request
    $publication->url;       // 'https://www.elysee.fr/emmanuel-macron/2026/11/16/visite-detat-au-cambodge…'
    $publication->title;     // 'Visite d'État au Cambodge et Sommet de la Francophonie.'
    $publication->date;
    $publication->section;   // 'Article', 'Communiqué de presse', 'Conseil des ministres'
    $publication->tags;      // ['International', 'Cambodge']
    $publication->body;      // its own words, paragraphs a blank line apart
    foreach ($publication->texts as $text) {
        $text->title;        // 'Discours du Président de la République au Sommet de la francophonie.'
        $text->date;         // the day it was delivered
        $text->content;      // the text in full
    }
}
```

A release is its own `body`; an article on a visit holds the speeches delivered there as `texts`, each read once — a video's transcript repeats one.

```yaml
wexample_symfony_remote_elysee:
  user_agent: 'my-app/1.0'
  cache_dir: '%kernel.project_dir%/var/remote/elysee'
  listings: ['/toutes-les-actualites', '/agenda', '/plan-du-site']
  prefixes: ['emmanuel-macron']   # the presidents' sections: /<prefix>/YYYY/MM/DD/<slug>, dossiers /<prefix>/<slug>
  delay: 1000          # milliseconds between two requests
  attempts: 3
  retry_delay: 5       # seconds, times the attempt number
```
