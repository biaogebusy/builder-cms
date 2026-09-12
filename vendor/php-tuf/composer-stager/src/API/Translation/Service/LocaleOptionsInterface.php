<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Translation\Service;

/**
 * Provides locale values for the translation system.
 *
 * @package Translation
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface LocaleOptionsInterface
{
    /** Gets the default locale. */
    public function default(): string;
}
