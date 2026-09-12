<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Translation\Service;

use Symfony\Contracts\Translation\TranslatorInterface as SymfonyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait as SymfonyTranslatorTrait;

/**
 * @package Translation
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class SymfonyTranslatorProxy implements SymfonyTranslatorProxyInterface
{
    // The Symfony translator trait returns different values based on
    // host details. Eliminate the variability by hard coding a value.
    private const LOCALE = 'en_US';

    public function trans(
        string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = self::LOCALE,
    ): string {
        return $this->symfonyTranslator()->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(): string
    {
        return self::LOCALE;
    }

    private function symfonyTranslator(): SymfonyTranslatorInterface
    {
        // Wrap the Symfony translator trait rather than using it directly
        // so as not to expose methods that aren't on the interface. Note: It
        // might seem more intuitive to save this to a class property in the
        // constructor, but that turns out to make PHP fail if you try to
        // serialize the class--as PHPBench does, for example:
        // ```
        // PHP Fatal error: Uncaught Exception: Serialization of
        // 'Symfony\Contracts\Translation\TranslatorInterface@anonymous' is not
        // allowed in /private/var/folders/h8/q4v7cc2s7db2nrc8pp6c6j5w0000gn/T/PhpBenchDrUj7Y:30
        // ```
        return new class() implements SymfonyTranslatorInterface {
            use SymfonyTranslatorTrait;
        };
    }
}
