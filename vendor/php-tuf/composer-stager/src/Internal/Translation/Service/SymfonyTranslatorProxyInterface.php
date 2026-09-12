<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Translation\Service;

use Symfony\Contracts\Translation\TranslatorInterface as SymfonyTranslatorInterface;

/**
 * Provides a thin wrapper around Symfony's default translator implementation.
 *
 * This is necessary because Symfony Translation Contracts doesn't provide an
 * injectable class--only a trait--and we don't want to depend on the full
 * Translation component to get one. Neither do we want to fork any part of it.
 *
 * @package Translation
 *
 * @internal Don't depend directly on this interface. It may be changed or removed at any time without notice.
 */
interface SymfonyTranslatorProxyInterface extends SymfonyTranslatorInterface
{
}
