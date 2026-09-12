<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Path\Value;

/**
 * Defines a list of path strings.
 *
 * @package Path
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface PathListInterface
{
    /**
     * Returns all path strings as given, canonicalized but unresolved.
     *
     * In other words, directory separators will be normalized and complex
     * paths will be simplified, but they will not be made absolute.
     *
     * Only unique values will be returned, i.e., duplicates will be omitted.
     *
     * @return array<string>
     */
    public function getAll(): array;

    /**
     * Adds a list of raw path strings.
     *
     * Path strings must be relative, e.g., "example" or "../example" but not
     * "/var/www/example". They will be resolved according to the context, e.g.,
     * relative to the active and staging directories, respectively, when syncing
     * files. Nothing needs to actually exist at the paths in any context
     */
    public function add(string ...$paths): void;
}
