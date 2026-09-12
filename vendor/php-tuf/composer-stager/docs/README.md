# Composer Stager

[![Latest stable version](https://poser.pugx.org/php-tuf/composer-stager/v/stable)](https://packagist.org/packages/php-tuf/composer-stager)
[![Tests status](https://github.com/php-tuf/composer-stager/actions/workflows/main.yml/badge.svg?branch=main)](https://github.com/php-tuf/composer-stager/actions/workflows/main.yml)
[![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen.svg?style=flat)](https://github.com/php-tuf/composer-stager/actions/workflows/main.yml) <!-- A static "100%" value can be used safely here because PHPUnit will fail builds if coverage falls below that. See the extensions in phpunit.xml.dist. -->
[![PHPStan](https://img.shields.io/badge/PHPStan-max-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)

Composer Stager makes long-running Composer commands safe to run on a codebase in production by "staging" them--performing them on a non-live copy of the codebase and syncing back the result for the least possible downtime.

- [Whom is it for?](#whom-is-it-for)
- [Why is it needed?](#why-is-it-needed)
- [Installation](#installation)
- [Usage](#usage)
- [Configuring services](#configuring-services)
- [Example](#example)
- [Contributing](#contributing)

## Whom is it for?

Composer Stager enables PHP products and frameworks (like [Drupal](https://drupal.org/)) to provide automated Composer-based self-updates for users without access to more robust solutions like [staged](https://en.wikipedia.org/wiki/Development,_testing,_acceptance_and_production) and [blue-green](https://martinfowler.com/bliki/BlueGreenDeployment.html) deployments--on restrictive or low-cost hosting, for example, or with little or no budget or development staff. It could conceivably be used with custom Composer-based apps, as well. It is not intended for end users.

## Why is it needed?

It may not be obvious at first that a tool like this is really necessary. Why not just use Composer in-place? Or why not just rsync files out and back? It turns out that the problem is incredibly complex, and the edge cases are myriad:

- You can't use Composer directly on a live codebase, because long-running commands put it in an unknown in-between state, and failures can irrecoverably corrupt it. The only safe option is to copy it elsewhere, run the commands there, and sync the result back to the live version. But...

- You may not have write access to directories outside the codebase--especially on low end shared hosting--so you must provide a (more complicated) alternative strategy using a subdirectory of the live codebase.

- You can't assume the availability of such tools as rsync, so you must provide detection and fallback capabilities.

- There are lots of cross-platform issues, including Unix vs. Windows paths, process execution peculiarities, disabled PHP functions, and symlink support, to name a few.

- Symlinks represent a problem space unto themselves, with as many logical issues as technical ones.

- Error messages must be translatable (i18n/l10n).

- You have to account for non-code files, like user uploads, cache files, and logs that may be changed in the live codebase while Composer commands are being run on the copy and could be clobbered when syncing it back.

- Failure to handle any of these challenges can easily have catastrophic results, including data loss or complete destruction of a live codebase. You need to anticipate and prevent them and provide actionable user feedback.

The list could go on. It should be obvious by now that a dedicated library is warranted.

## Installation

The library is installed via Composer:

```shell
composer require php-tuf/composer-stager
```

## Usage

It is invoked via its PHP API. Given a configured service container ([see below](#configuring-services)), its services can be used like the following, for example:

```php
class Updater
{
    public function __construct(
        private readonly BeginnerInterface $beginner,
        private readonly StagerInterface $stager,
        private readonly CommitterInterface $committer,
        private readonly CleanerInterface $cleaner,
        private readonly PathFactoryInterface $pathFactory,
        private readonly PathListFactoryInterface $pathListFactory,
    ) {
    }

    public function update(): void
    {
        $activeDir = $this->pathFactory->create('/var/www/public');
        $stagingDir = $this->pathFactory->create('/var/www/staging');
        $exclusions = $this->pathListFactory->create(
            'cache',
            'uploads',
        );

        // Copy the codebase to the staging directory.
        $this->beginner->begin($activeDir, $stagingDir, $exclusions);

        // Run a Composer command on it.
        $this->stager->stage([
            'require',
            'example/package',
            '--update-with-all-dependencies',
        ], $activeDir, $stagingDir);

        // Sync the changes back to the active directory.
        $this->committer->commit($stagingDir, $activeDir, $exclusions);

        // Remove the staging directory.
        $this->cleaner->clean($stagingDir);
    }
}
```

## Configuring services

Composer Stager uses the [dependency injection](https://en.wikipedia.org/wiki/Dependency_injection) pattern, and its services are best accessed via a container that supports autowiring, e.g., [Symfony's](https://symfony.com/doc/current/service_container.html). (Manual wiring is brittle and therefore not officially supported.) See [`services.yml`](services.yml) for a working example.

## Example

A complete, functioning example implementation of Composer Stager can be found in the [Composer Stager Console](https://github.com/php-tuf/composer-stager-console) repository.

## Contributing

[Pull requests](https://github.com/php-tuf/composer-stager/pulls?q=is%3Apr+is%3Aopen+sort%3Aupdated-desc) are welcome. For major changes, please [open an issue](https://github.com/php-tuf/composer-stager/issues?q=is%3Aissue+is%3Aopen+sort%3Aupdated-desc) first to discuss what you would like to change. Observe [the coding standards](https://github.com/php-tuf/composer-stager/wiki/Coding-standards-&-style-guide), and if you're able, add and update [the tests](https://github.com/php-tuf/composer-stager/wiki/Automated-testing-&-analysis) as appropriate.

---

[More info in the Wiki.](https://github.com/php-tuf/composer-stager/wiki)
