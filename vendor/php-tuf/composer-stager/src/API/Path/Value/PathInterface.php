<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Path\Value;

/**
 * Handles a filesystem path string.
 *
 * The path string may be absolute or relative to the current working directory
 * as returned by `getcwd()` at runtime, e.g., "/var/www/example" or "example".
 * Nothing needs to actually exist at the path. Paths beginning with a protocol,
 * e.g., "ftp://" or "file:///", are unsupported, and their behavior is unspecified.
 *
 * To interact with the actual filesystem at this path, see
 * {@see \PhpTuf\ComposerStager\API\Filesystem\Service\FilesystemInterface}
 *
 * @package Path
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface PathInterface
{
    /**
     * Gets the fully resolved, absolute path string without trailing slash.
     *
     * Resolves this path relative to the current working directory as returned
     * by `getcwd()` at runtime, e.g., "/var/www/example" given a path of
     * "example" and a working directory of "/var/www".
     */
    public function absolute(): string;

    /** Determines whether the original path string as given is absolute, without resolving it. */
    public function isAbsolute(): bool;

    /** Determines whether the original path string as given is relative, without resolving it. */
    public function isRelative(): bool;

    /**
     * Gets the fully resolved, absolute path string without trailing slash, relative to another given path.
     *
     * Resolves this path relative to the fully resolved path of the given path
     * object, e.g., "/usr/local/example" given a path of "example" and a Path
     * argument that resolves to "/usr/local". If this path is not a descendant
     * of the given path, this path will simply be resolved and returned, e.g.,
     * "/var/one/two/three" relative to "/var/four/five/six" would return
     * "/var/one/two/three".
     */
    public function relative(PathInterface $basePath): string;
}
