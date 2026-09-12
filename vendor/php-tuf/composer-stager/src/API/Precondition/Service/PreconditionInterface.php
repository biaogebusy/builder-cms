<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Precondition\Service;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;

/**
 * Defines a precondition for an API operation and how to verify it.
 *
 * @package Precondition
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface PreconditionInterface
{
    /**
     * Gets the name.
     *
     * E.g., "Example dependency".
     */
    public function getName(): TranslatableInterface;

    /**
     * Gets a short description.
     *
     * This should probably be kept to one or two sentences, e.g., "The example
     * dependency is required in order to perform some relevant action."
     */
    public function getDescription(): TranslatableInterface;

    /**
     * Gets a short status message.
     *
     * This reflects the actual status of the precondition at runtime and may
     * include details for resolving an unfulfilled precondition, e.g., "The
     * example dependency is ready," or if unfulfilled, "The example dependency
     * cannot be found. Make sure it's installed." If the precondition has
     * unfulfilled leaves, the status message from the first one will be returned.
     *
     * @param int<0, max> $timeout
     *   An optional process timeout (maximum runtime) in seconds. If set to
     *   zero (0), no time limit is imposed.
     */
    public function getStatusMessage(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): TranslatableInterface;

    /**
     * Determines whether the precondition is fulfilled.
     *
     * @param int<0, max> $timeout
     *   An optional process timeout (maximum runtime) in seconds. If set to
     *   zero (0), no time limit is imposed.
     */
    public function isFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): bool;

    /**
     * Asserts that the precondition is fulfilled.
     *
     * @param int<0, max> $timeout
     *   An optional process timeout (maximum runtime) in seconds. If set to
     *   zero (0), no time limit is imposed.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\PreconditionException
     *   If the precondition is unfulfilled.
     */
    public function assertIsFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void;

    /**
     * Returns a flat array of all concrete preconditions in the contained tree.
     *
     * This may be valuable for a creating a status report, for example.
     *
     * @return array<\PhpTuf\ComposerStager\API\Precondition\Service\PreconditionInterface>
     */
    public function getLeaves(): array;
}
