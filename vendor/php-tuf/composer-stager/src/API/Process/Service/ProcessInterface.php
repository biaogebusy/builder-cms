<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Process\Service;

/**
 * Builds and runs shell commands.
 *
 * @package Process
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface ProcessInterface
{
    /** The default process timeout. */
    public const DEFAULT_TIMEOUT = 120;

    /**
     * Gets the array of environment variables to be set while running the process.
     *
     * @return array<string|\Stringable>
     *
     * @see \PhpTuf\ComposerStager\API\Process\Service\ProcessInterface::setEnv
     */
    public function getEnv(): array;

    /**
     * Returns the current error output of the process (STDERR).
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\LogicException
     *   If the process is not started.
     *
     * @see \PhpTuf\ComposerStager\API\Process\Service\ProcessInterface::getOutput
     */
    public function getErrorOutput(): string;

    /**
     * Returns the current output of the process (STDOUT).
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\LogicException
     *   If the process is not started.
     *
     * @see \PhpTuf\ComposerStager\API\Process\Service\ProcessInterface::getErrorOutput
     */
    public function getOutput(): string;

    /**
     * Runs the process.
     *
     * This is identical to run() except that an exception is thrown if the process exits with a non-zero exit code.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\RuntimeException
     *   If the process doesn't terminate successfully.
     *
     * @see \PhpTuf\ComposerStager\API\Process\Service\ProcessInterface::run
     */
    public function mustRun(?OutputCallbackInterface $callback = null): void;

    /**
     * Runs the process.
     *
     * @return int
     *   The exit status code: 0 for success or non-zero for any error condition.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\RuntimeException
     *   If the process fails to run for any reason.
     *
     * @see \PhpTuf\ComposerStager\API\Process\Service\ProcessInterface::run
     */
    public function run(?OutputCallbackInterface $callback = null): int;

    /**
     * Sets an array of environment variables to set while running the process.
     *
     * @param array<string|\Stringable> $env
     *   An array of environment variables, keyed by variable name with corresponding
     *   string or stringable values. In addition to those explicitly specified,
     *   environment variables set on your system will be inherited. You can
     *   prevent this by setting to `false` variables you want to remove. Example:
     *   ```php
     *   $process->setEnv(
     *       'STRING_VAR' => 'a string',
     *       'STRINGABLE_VAR' => new StringableObject(),
     *       'REMOVE_ME' => false,
     *   );
     *   ```
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\InvalidArgumentException
     *   If the given environment variables contain invalid variable names or values.
     *
     * @see \PhpTuf\ComposerStager\API\Process\Service\ProcessInterface::getEnv
     */
    public function setEnv(array $env): void;

    /**
     * Sets the process timeout (max. runtime) in seconds.
     *
     * @param int<0, max> $timeout
     *   An optional process timeout (maximum runtime) in seconds. If set to
     *   zero (0), no time limit is imposed.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\InvalidArgumentException
     *   If the given timeout is negative.
     */
    public function setTimeout(int $timeout = self::DEFAULT_TIMEOUT): void;
}
