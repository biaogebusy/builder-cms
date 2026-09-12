<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Core;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;

/**
 * Begins the staging process by copying the active directory to the staging directory.
 *
 * @package Core
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface BeginnerInterface
{
    /**
     * Begins the staging process.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $activeDir
     *   The active directory.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $stagingDir
     *   The staging directory.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathListInterface|null $exclusions
     *   Paths to exclude, relative to the active directory. Careful use of
     *   exclusions can reduce execution time and disk usage. Two kinds of files
     *   and directories are good candidates for exclusion:
     *   - Those that will (or might) be changed in the active directory between
     *     beginning and committing and should not be overwritten in the active
     *     directory. This might include user upload directories, for example.
     *   - Those that definitely will NOT be changed or needed between beginning
     *     and committing and will therefore have no effect on the final outcome.
     *     This might include your version control directory, e.g., ".git", or
     *     certain kinds of caches, e.g., of HTTP responses.
     *
     *   With rare exception, you should use the same exclusions when beginning
     *   as when committing.
     * @param \PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface|null $callback
     *   An optional PHP callback to run whenever there is process output.
     * @param int<0, max> $timeout
     *   An optional process timeout (maximum runtime) in seconds. If set to
     *   zero (0), no time limit is imposed.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\PreconditionException
     *   If the preconditions are unfulfilled.
     * @throws \PhpTuf\ComposerStager\API\Exception\RuntimeException
     *   If the operation fails.
     *
     * @see \PhpTuf\ComposerStager\Internal\Core\CommitterInterface::commit
     */
    public function begin(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        ?OutputCallbackInterface $callback = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void;
}
