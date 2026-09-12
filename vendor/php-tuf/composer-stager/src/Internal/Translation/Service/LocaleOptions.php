<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Translation\Service;

use PhpTuf\ComposerStager\API\Translation\Service\LocaleOptionsInterface;

/**
 * @package Translation
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class LocaleOptions implements LocaleOptionsInterface
{
    // This constant is private so that no project code depends
    // directly on it, which would make it impossible to override.
    private const DEFAULT = 'en_US';

    public function default(): string
    {
        return self::DEFAULT;
    }
}
