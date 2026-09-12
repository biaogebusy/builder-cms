<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Process\Service;

use PhpTuf\ComposerStager\API\Exception\InvalidArgumentException;
use PhpTuf\ComposerStager\API\Exception\LogicException;
use PhpTuf\ComposerStager\API\Exception\RuntimeException;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\Internal\Process\Factory\SymfonyProcessFactoryInterface;
use PhpTuf\ComposerStager\Internal\Translation\Factory\TranslatableAwareTrait;
use Symfony\Component\Process\Process as SymfonyProcess;
use Throwable;

/**
 * @package Process
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class Process implements ProcessInterface
{
    use TranslatableAwareTrait;

    private readonly SymfonyProcess $symfonyProcess;

    /**
     * @param array<string> $command
     *   The command parts exactly as they would be typed in the terminal.
     *   There's no need to escape them in any way, only to separate them. Example:
     *   ```php
     *   $command = [
     *       'composer',
     *       'require',
     *       'example/package:"^1 || ^2"',
     *       '--with-all-dependencies',
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
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\LogicException
     *   If the process cannot be created due to host configuration.
     */
    public function __construct(
        private readonly SymfonyProcessFactoryInterface $symfonyProcessFactory,
        TranslatableFactoryInterface $translatableFactory,
        array $command = [],
        ?PathInterface $cwd = null,
        array $env = [],
    ) {
        $this->setTranslatableFactory($translatableFactory);
        $this->symfonyProcess = $this->symfonyProcessFactory->create($command, $cwd, $env);
    }

    public function getEnv(): array
    {
        return $this->symfonyProcess->getEnv();
    }

    public function getErrorOutput(): string
    {
        try {
            return $this->symfonyProcess->getErrorOutput();
        } catch (Throwable $e) {
            throw new LogicException($this->t(
                'Failed to get process error output: %details',
                $this->p(['%details' => $e->getMessage()]),
                $this->d()->exceptions(),
            ), 0, $e);
        }
    }

    public function getOutput(): string
    {
        try {
            return $this->symfonyProcess->getOutput();
        } catch (Throwable $e) {
            throw new LogicException($this->t(
                'Failed to get process output: %details',
                $this->p(['%details' => $e->getMessage()]),
                $this->d()->exceptions(),
            ), 0, $e);
        }
    }

    public function mustRun(?OutputCallbackInterface $callback = null): void
    {
        try {
            $callbackAdapter = new OutputCallbackAdapter($callback);
            $this->symfonyProcess->mustRun($callbackAdapter);
        } catch (Throwable $e) {
            throw new RuntimeException($this->t(
                'Failed to run process: %details',
                $this->p(['%details' => $e->getMessage()]),
                $this->d()->exceptions(),
            ), 0, $e);
        }
    }

    public function run(?OutputCallbackInterface $callback = null): int
    {
        try {
            $callbackAdapter = new OutputCallbackAdapter($callback);

            return $this->symfonyProcess->run($callbackAdapter);
        } catch (Throwable $e) {
            throw new RuntimeException($this->t(
                'Failed to run process: %details',
                $this->p(['%details' => $e->getMessage()]),
                $this->d()->exceptions(),
            ), 0, $e);
        }
    }

    public function setEnv(array $env): void
    {
        $this->assertValidEnv($env);
        $this->symfonyProcess->setEnv($env);
    }

    public function setTimeout(int $timeout = self::DEFAULT_TIMEOUT): void
    {
        try {
            $this->symfonyProcess->setTimeout($timeout);
        } catch (Throwable $e) {
            throw new InvalidArgumentException($this->t(
                'Failed to set process timeout: %details',
                $this->p(['%details' => $e->getMessage()]),
                $this->d()->exceptions(),
            ), 0, $e);
        }
    }

    /**
     * @param array<string|\Stringable> $env
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\InvalidArgumentException
     */
    private function assertValidEnv(array $env): void
    {
        foreach ($env as $name => $value) {
            $this->assertValidEnvName($name);
            $this->assertValidEnvValue($value);
        }
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\InvalidArgumentException */
    private function assertValidEnvName(mixed $name): void
    {
        if (is_string($name) && $name !== '') {
            return;
        }

        throw new InvalidArgumentException($this->t(
            'Environment variable names must be non-zero-length strings. Got %name.',
            $this->p(['%name' => var_export($name, true)]),
            $this->d()->exceptions(),
        ));
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\InvalidArgumentException */
    private function assertValidEnvValue(mixed $value): void
    {
        // Is string or stringable.
        if (is_string($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return;
        }

        throw new InvalidArgumentException($this->t(
            'Environment variable values must be strings, stringable, or false to unset. Got %name.',
            $this->p(['%name' => (is_scalar($value) ? var_export($value, true) : get_debug_type($value))]),
            $this->d()->exceptions(),
        ));
    }
}
