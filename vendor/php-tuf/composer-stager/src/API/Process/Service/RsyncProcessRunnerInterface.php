<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Process\Service;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;

/**
 * Runs rsync commands.
 *
 * @package Process
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface RsyncProcessRunnerInterface
{
    /**
     * Runs a given rsync command.
     *
     * @param array<string> $command
     *   The command to run and its arguments as separate string values. Example:
     *   ```php
     *   $command = [
     *       // "rsync" is implied.
     *       '--recursive',
     *       'path/to/source',
     *       'path/to/destination',
     *   ];
     *   ```
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface|null $cwd
     *   The current working directory (CWD) for the process. If set to null,
     *   the CWD of the current PHP process will be used.
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
     * @param \PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface|null $callback
     *   An optional PHP callback to run whenever there is process output.
     * @param int<0, max> $timeout
     *   An optional process timeout (maximum runtime) in seconds. If set to
     *   zero (0), no time limit is imposed.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\LogicException
     *   If the command process cannot be created due to host configuration.
     * @throws \PhpTuf\ComposerStager\API\Exception\RuntimeException
     *   If the operation fails.
     *
     * @see https://symfony.com/doc/current/components/process.html#running-processes-asynchronously
     */
    public function run(
        array $command,
        ?PathInterface $cwd = null,
        array $env = [],
        ?OutputCallbackInterface $callback = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void;
}
