<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Path\Value;

use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\Internal\Path\Service\PathHelperInterface;

/**
 * @package Path
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class PathList implements PathListInterface
{
    /** @var array<string> */
    private array $paths;

    public function __construct(private readonly PathHelperInterface $pathHelper, string ...$paths)
    {
        $this->paths = $paths;
    }

    /** @return array<string> */
    public function getAll(): array
    {
        return array_values(array_unique(array_map(
            fn ($path): string => $this->pathHelper->canonicalize($path),
            $this->paths,
        )));
    }

    public function add(string ...$paths): void
    {
        $this->paths = [...$this->paths, ...$paths];
    }
}
