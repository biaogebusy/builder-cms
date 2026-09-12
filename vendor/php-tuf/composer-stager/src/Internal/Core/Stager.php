<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Core;

use PhpTuf\ComposerStager\API\Core\StagerInterface;
use PhpTuf\ComposerStager\API\Exception\ExceptionInterface;
use PhpTuf\ComposerStager\API\Exception\InvalidArgumentException;
use PhpTuf\ComposerStager\API\Exception\RuntimeException;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\StagerPreconditionsInterface;
use PhpTuf\ComposerStager\API\Process\Service\ComposerProcessRunnerInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\Internal\Translation\Factory\TranslatableAwareTrait;

/**
 * @package Core
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class Stager implements StagerInterface
{
    use TranslatableAwareTrait;

    public function __construct(
        private readonly ComposerProcessRunnerInterface $composerRunner,
        private readonly StagerPreconditionsInterface $preconditions,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        $this->setTranslatableFactory($translatableFactory);
    }

    public function stage(
        array $composerCommand,
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?OutputCallbackInterface $callback = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        $this->preconditions->assertIsFulfilled($activeDir, $stagingDir);

        $this->validateCommand($composerCommand);

        $this->runCommand($stagingDir, $composerCommand, $callback, $timeout);
    }

    /**
     * @param array<string> $composerCommand
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\InvalidArgumentException
     */
    private function validateCommand(array $composerCommand): void
    {
        if ($composerCommand === []) {
            throw new InvalidArgumentException($this->t(
                'The Composer command cannot be empty',
                null,
                $this->d()->exceptions(),
            ));
        }

        if (reset($composerCommand) === 'composer') {
            throw new InvalidArgumentException($this->t(
                'The Composer command cannot begin with "composer"--it is implied',
                null,
                $this->d()->exceptions(),
            ));
        }

        if (array_key_exists('--working-dir', $composerCommand) || array_key_exists('-d', $composerCommand)) {
            throw new InvalidArgumentException($this->t(
                'Cannot stage a Composer command containing the "--working-dir" (or "-d") option',
                null,
                $this->d()->exceptions(),
            ));
        }
    }

    /**
     * @param array<string> $composerCommand
     * @param int<0, max> $timeout
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\RuntimeException
     */
    private function runCommand(
        PathInterface $stagingDir,
        array $composerCommand,
        ?OutputCallbackInterface $callback,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        $command = ['--working-dir=' . $stagingDir->absolute(), ...$composerCommand];

        try {
            $this->composerRunner->run($command, null, [], $callback, $timeout);
        } catch (ExceptionInterface $e) {
            throw new RuntimeException($e->getTranslatableMessage(), 0, $e);
        }
    }
}
