<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Path\Factory;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;

/**
 * Creates path value objects.
 *
 * @package Path
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface PathFactoryInterface
{
    /**
     * Creates a path value object from a string.
     *
     * @param string $path
     *   The path string may be absolute or relative to the current working
     *   directory as returned by `getcwd()` at runtime, e.g., "/var/www/example"
     *   or "example". Nothing needs to actually exist at the path.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface|null $basePath
     *   Optionally override the base path used for relative paths.
     *   Nothing needs to actually exist at the path. Therefore, it is simply
     *   assumed to represent a directory, as opposed to a file--even if
     *   it has an extension, which is no guarantee of type.
     */
    public function create(string $path, ?PathInterface $basePath = null): PathInterface;
}
