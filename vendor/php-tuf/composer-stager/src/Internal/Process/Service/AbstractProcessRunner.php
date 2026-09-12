<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Process\Service;

use PhpTuf\ComposerStager\API\Finder\Service\ExecutableFinderInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Factory\ProcessFactoryInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\Internal\Translation\Factory\TranslatableAwareTrait;

/**
 * Provides a base for process runners for consistent process creation and
 * exception-handling.
 *
 * @package Process
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
abstract class AbstractProcessRunner
{
    use TranslatableAwareTrait;

    /** Returns the executable name, e.g., "composer" or "rsync". */
    abstract protected function executableName(): string;

    public function __construct(
        private readonly ExecutableFinderInterface $executableFinder,
        private readonly ProcessFactoryInterface $processFactory,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        $this->setTranslatableFactory($translatableFactory);
    }

    /**
     * @param array<string> $command
     *   The command arguments as separate string values, e.g.,
     *   ['require', 'example/package'] or ['source', 'destination']. The return
     *   value of ::executableName() will be automatically prepended.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface|null $cwd
     *   The current working directory (CWD) for the process. If set to null,
     *   the CWD of the current PHP process will be used.
     * @param \PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface|null $callback
     *   An optional PHP callback to run whenever there is process output.
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
     * @param int<0, max> $timeout
     *    An optional process timeout (maximum runtime) in seconds. If set to
     *    zero (0), no time limit is imposed.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\InvalidArgumentException
     *   If the given timeout is negative.
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
    ): void {
        array_unshift($command, $this->findExecutable());
        $process = $this->processFactory->create($command, $cwd, $env);
        $process->setTimeout($timeout);
        $process->mustRun($callback);
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\LogicException */
    private function findExecutable(): string
    {
        $name = $this->executableName();

        return $this->executableFinder->find($name);
    }
}
