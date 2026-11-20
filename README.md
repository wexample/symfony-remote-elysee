# symfony-remote-elysee

Version: 2.0.0

elysee.fr as a symfony-remote: the publications of the Présidence de la République — releases, articles, the texts of the speeches —, read politely and kept on disk

## Table of Contents

- [Installation](#installation)
- [Introduction](#introduction)

## Installation

```php
// config/bundles.php
Wexample\SymfonyRemote\WexampleSymfonyRemoteBundle::class => ['all' => true],
Wexample\SymfonyRemoteElysee\WexampleSymfonyRemoteElyseeBundle::class => ['all' => true],
```

Name the app in `user_agent`. The publications read are kept under `var/remote/elysee` (`cache_dir`), compressed — a few kilobytes each —: out of the kernel's cache, and worth a volume in production, or every crawl starts over.

`symfony-remote-elysee` reads the publications of elysee.fr from a Symfony app: the Présidence's releases, the articles on the President's visits, the reports of the Conseil des ministres, and the texts they hold in full — the President's speeches, addresses and letters.

The site's contents are under Licence Ouverte: its legal notice holds the President's speeches and the Présidence's releases free to reproduce, crediting the author and « elysee.fr », with a link to the original. The photographs and videos are not: the package reads none.

elysee.fr lists its publications only to its own search, and its sitemaps answer us 403: the package reads the site as a visitor would, from the pages listing the latest publications, then from one publication to those it links to and through the dossiers listing them — a visit, a theme —, which reach back through the archive: one request at a time, a pause between two. What it read is kept on disk and never asked for again.

`ElyseeClient` is a `symfony-remote`, so `remote:status` checks the latest publications are listed. A request that fails is tried again a few times, then raised as php-remote's `RemoteException`, as is a page the package can no longer read. Run the calls through `RemoteGuard` where the app must go on without them.
