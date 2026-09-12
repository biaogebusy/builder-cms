<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Path\Factory;

use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;

/**
 * Creates path list value objects.
 *
 * @package Path
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface PathListFactoryInterface
{
    /**
     * Creates a path list value object from a list of relative path strings.
     *
     * Example:
     * ```php
     * $pathListFactory->create(
     *     'cache',
     *     'uploads',
     * );
     * ```
     */
    public function create(string ...$paths): PathListInterface;
}
