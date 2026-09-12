<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Path\Service;

/**
 * @package Path
 *
 * @internal Don't depend directly on this interface. It may be changed or removed at any time without notice.
 */
interface PathHelperInterface
{
    /** Canonicalizes the given path. */
    public function canonicalize(string $path): string;

    /** Determines whether the given path is absolute. */
    public function isAbsolute(string $path): bool;

    /** Determines whether the first path is a descendant of the second one. */
    public function isDescendant(string $descendant, string $ancestor): bool;

    /** Determines whether the given path is relative. */
    public function isRelative(string $path): bool;
}
