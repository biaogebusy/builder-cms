<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Core;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;

/**
 * Removes the staging directory.
 *
 * @package Core
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface CleanerInterface
{
    /**
     * Removes the staging directory.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $activeDir
     *   The active directory.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $stagingDir
     *   The staging directory.
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
     */
    public function clean(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?OutputCallbackInterface $callback = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void;
}
