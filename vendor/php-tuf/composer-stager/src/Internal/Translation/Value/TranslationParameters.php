<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Translation\Value;

use PhpTuf\ComposerStager\API\Translation\Value\TranslationParametersInterface;

/**
 * Handles an array of translation parameters.
 *
 * @package Translation
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class TranslationParameters implements TranslationParametersInterface
{
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
    public function __construct(private array $parameters = [])
    {
        $this->parameters = $this->setValidParameters($parameters);
    }

    public function getAll(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return array<string, string>
     */
    private function setValidParameters(array $parameters): array
    {
        $validParameters = [];

        // Throwing exceptions here would create a nasty infinite recursion
        // problem that would end up exposing client code to low-level errors
        // with virtually every service call. Instead, use an assertion to
        // surface errors during development and remove invalid parameters
        // elements in case any escape to production.
        foreach ($parameters as $placeholder => $value) {
            if (!is_string($value)) {
                assert(false, sprintf(
                    'Placeholder values must be strings. Got %s.',
                    get_debug_type($value),
                ));

                /** @noinspection PhpUnreachableStatementInspection */
                continue;
            }

            $pattern = '/^%\w+$/';

            if (!is_string($placeholder) || preg_match($pattern, $placeholder) !== 1) {
                assert(false, sprintf(
                    'Placeholders must be in the form %s, i.e., a leading percent sign (%%) followed '
                    . 'by one or more alphanumeric characters and underscores, e.g., "%%example". Got %s.',
                    $pattern,
                    var_export($placeholder, true),
                ));

                /** @noinspection PhpUnreachableStatementInspection */
                continue;
            }

            // The parameter is valid.
            $validParameters[$placeholder] = $value;
        }

        return $validParameters;
    }
}
