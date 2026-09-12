<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Exception\ExceptionInterface;
use PhpTuf\ComposerStager\API\Exception\LogicException;
use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Finder\Service\ExecutableFinderInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\RsyncIsAvailableInterface;
use PhpTuf\ComposerStager\API\Process\Factory\ProcessFactoryInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class RsyncIsAvailable extends AbstractPrecondition implements RsyncIsAvailableInterface
{
    private string $executablePath;

    public function __construct(
        EnvironmentInterface $environment,
        private readonly ExecutableFinderInterface $executableFinder,
        private readonly ProcessFactoryInterface $processFactory,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        parent::__construct($environment, $translatableFactory);
    }

    public function getName(): TranslatableInterface
    {
        return $this->t('Rsync');
    }

    public function getDescription(): TranslatableInterface
    {
        return $this->t('Rsync must be available in order to sync files between the active and staging directories.');
    }

    protected function getFulfilledStatusMessage(): TranslatableInterface
    {
        return $this->t('Rsync is available.');
    }

    protected function doAssertIsFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        $this->assertExecutableExists();
        $this->assertIsActuallyRsync();
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\PreconditionException */
    private function assertExecutableExists(): void
    {
        try {
            $this->executablePath = $this->executableFinder->find('rsync');
        } catch (LogicException $e) {
            throw new PreconditionException($this, $this->t(
                'Cannot find rsync.',
                null,
                $this->d()->exceptions(),
            ), 0, $e);
        }
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\PreconditionException */
    private function assertIsActuallyRsync(): void
    {
        $process = $this->getProcess();

        if (!$this->isValidExecutable($process)) {
            throw new PreconditionException($this, $this->t(
                'The rsync executable at %path is invalid.',
                $this->p(['%path' => $this->executablePath]),
                $this->d()->exceptions(),
            ));
        }
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\PreconditionException */
    private function getProcess(): ProcessInterface
    {
        try {
            return $this->processFactory->create([
                $this->executablePath,
                '--version',
            ]);
        } catch (LogicException $e) {
            throw new PreconditionException($this, $this->t(
                'Cannot check for rsync due to a host configuration problem: %details',
                $this->p(['%details' => $e->getMessage()]),
                $this->d()->exceptions(),
            ), 0, $e);
        }
    }

    private function isValidExecutable(ProcessInterface $process): bool
    {
        try {
            $process->mustRun();
            $output = $process->getOutput();
        } catch (ExceptionInterface) {
            return false;
        }

        // Look for "rsync version" at the beginning of any line of output (ignoring spacing).
        return (bool) preg_match('/^ *rsync *version /m', $output);
    }
}
