<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\FileSyncer\Service;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;

/**
 * Recursively syncs files from one directory to another.
 *
 * @package FileSyncer
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface FileSyncerInterface
{
    /**
     * Recursively syncs files from one directory to another.
     *
     * Files in the destination will be overwritten by those in the source, even if
     * newer. Files in the destination that do not exist in the source will be deleted.
     * Excluded paths will be completely ignored and neither copied to nor deleted
     * from the destination. If the destination does not exist, it will be created.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $source
     *   The directory to sync files from.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $destination
     *   The directory to sync files to.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathListInterface|null $exclusions
     *   Paths to exclude, relative to the source directory. The destination
     *   directory is automatically excluded in order to prevent infinite
     *   recursion if it is a descendant of the source directory, i.e., if it is
     *   "underneath" or "inside" it.
     * @param \PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface|null $callback
     *   An optional PHP callback to run whenever there is process output.
     * @param int<0, max> $timeout
     *   An optional process timeout (maximum runtime) in seconds. If set to
     *   zero (0), no time limit is imposed.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     *   If the destination directory cannot be created.
     * @throws \PhpTuf\ComposerStager\API\Exception\LogicException
     *   If the source directory does not exist or is the same as the destination
     *   or if rsync cannot be found.
     */
    public function sync(
        PathInterface $source,
        PathInterface $destination,
        ?PathListInterface $exclusions = null,
        ?OutputCallbackInterface $callback = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void;
}
