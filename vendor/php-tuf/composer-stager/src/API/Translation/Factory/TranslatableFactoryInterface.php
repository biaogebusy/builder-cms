<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Translation\Factory;

use PhpTuf\ComposerStager\API\Translation\Service\DomainOptionsInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslationParametersInterface;

/**
 * Creates translatable objects.
 *
 * @package Translation
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface TranslatableFactoryInterface
{
    /** Creates a domain options object. */
    public function createDomainOptions(): DomainOptionsInterface;

    /**
     * Creates a translatable message.
     *
     * @param string $message
     *   A message containing optional placeholders corresponding to parameters (next). Example:
     *   ```php
     *   $message = 'Hello, %first_name %last_name.';
     *   ```
     * @param string|null $domain
     *   An arbitrary domain for grouping translations or null to use the default. See
     *   {@see \PhpTuf\ComposerStager\API\Translation\Service\DomainOptionsInterface}.
     */
    public function createTranslatableMessage(
        string $message,
        ?TranslationParametersInterface $parameters = null,
        ?string $domain = null,
    ): TranslatableInterface;

    /**
     * Creates a translation parameters object.
     *
     * @param array<string, string> $parameters
     *   An associative array keyed by placeholders with their corresponding substitution
     *   values. Placeholders must be in the form /^%\w+$/, i.e., a leading percent sign (%)
     *   followed by one or more alphanumeric characters and underscores, e.g., "%example".
     *   Values must be strings. Example:
     *   ```php
     *   $parameters = [
     *       '%first_name' => 'John',
     *       '%last_name' => 'Doe',
     *   ];
     *   ```
     */
    public function createTranslationParameters(array $parameters = []): TranslationParametersInterface;
}
