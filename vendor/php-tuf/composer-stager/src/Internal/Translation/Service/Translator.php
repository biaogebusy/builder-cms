<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Translation\Service;

use PhpTuf\ComposerStager\API\Translation\Service\DomainOptionsInterface;
use PhpTuf\ComposerStager\API\Translation\Service\LocaleOptionsInterface;
use PhpTuf\ComposerStager\API\Translation\Service\TranslatorInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslationParametersInterface;
use PhpTuf\ComposerStager\Internal\Translation\Value\TranslationParameters;
use Throwable;

/**
 * @package Translation
 *
 * This class HAPPENS to use a Symfony translator. However, there is no guarantee of functional
 * equivalence. Refer to the {@see \PhpTuf\ComposerStager\API\Translation\Service\TranslatorInterface}
 * and do not depend on undocumented behavior.
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class Translator implements TranslatorInterface
{
    public function __construct(
        private readonly DomainOptionsInterface $domainOptions,
        private readonly LocaleOptionsInterface $localeOptions,
        private readonly SymfonyTranslatorProxyInterface $symfonyTranslatorProxy,
    ) {
    }

    /** Only use this as a last resort, when standard dependency injection is literally impossible. */
    public static function create(): self
    {
        $domainOptions = new DomainOptions();
        $localOptions = new LocaleOptions();
        $symfonyTranslatorProxy = new SymfonyTranslatorProxy();

        return new self($domainOptions, $localOptions, $symfonyTranslatorProxy);
    }

    public function trans(
        string $message,
        ?TranslationParametersInterface $parameters = null,
        ?string $domain = null,
        ?string $locale = null,
    ): string {
        try {
            $parameters ??= new TranslationParameters();
            $domain ??= $this->domainOptions->default();
            $locale ??= $this->localeOptions->default();

            return $this->symfonyTranslatorProxy->trans($message, $parameters->getAll(), $domain, $locale);
        } catch (Throwable $e) {
            $message = sprintf('Translation error: %s', $e->getMessage());

            // Re/throwing an exception here would create a nasty infinite recursion
            // problem that would end up exposing client code to low-level errors
            // with virtually every service call. Instead, use an assertion to surface
            // errors during development and return the untranslated error message
            // in case any escape to production.
            assert(false, $message);

            /** @noinspection PhpUnreachableStatementInspection */
            return $message;
        }
    }

    public function getLocale(): string
    {
        return $this->localeOptions->default();
    }
}
