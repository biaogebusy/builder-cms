<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Translation\Service;

use PhpTuf\ComposerStager\API\Translation\Service\DomainOptionsInterface;

/**
 * @package Translation
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class DomainOptions implements DomainOptionsInterface
{
    // These constants are private so that no project code depends
    // directly on them, which would make them impossible to override.
    private const DEFAULT = 'messages';
    private const EXCEPTIONS = 'exceptions';

    public function default(): string
    {
        return self::DEFAULT;
    }

    public function exceptions(): string
    {
        return self::EXCEPTIONS;
    }
}
