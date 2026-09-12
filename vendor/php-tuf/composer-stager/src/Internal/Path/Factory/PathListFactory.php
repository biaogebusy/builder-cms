<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Path\Factory;

use PhpTuf\ComposerStager\API\Path\Factory\PathListFactoryInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\Internal\Path\Service\PathHelperInterface;
use PhpTuf\ComposerStager\Internal\Path\Value\PathList;

/**
 * @package Path
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class PathListFactory implements PathListFactoryInterface
{
    public function __construct(private readonly PathHelperInterface $pathHelper)
    {
    }

    public function create(string ...$paths): PathListInterface
    {
        return new PathList($this->pathHelper, ...$paths);
    }
}
