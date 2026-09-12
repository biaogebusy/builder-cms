<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use JsonException;
use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Exception\ExceptionInterface;
use PhpTuf\ComposerStager\API\Exception\LogicException;
use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Finder\Service\ExecutableFinderInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\ComposerIsAvailableInterface;
use PhpTuf\ComposerStager\API\Process\Service\ComposerProcessRunnerInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class ComposerIsAvailable extends AbstractPrecondition implements ComposerIsAvailableInterface
{
    private string $executablePath;

    public function __construct(
        private readonly ComposerProcessRunnerInterface $composerProcessRunner,
        EnvironmentInterface $environment,
        private readonly ExecutableFinderInterface $executableFinder,
        private readonly OutputCallbackInterface $outputCallback,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        parent::__construct($environment, $translatableFactory);
    }

    public function getName(): TranslatableInterface
    {
        return $this->t('Composer');
    }

    public function getDescription(): TranslatableInterface
    {
        return $this->t('Composer must be available in order to stage commands.');
    }

    protected function getFulfilledStatusMessage(): TranslatableInterface
    {
        return $this->t('Composer is available.');
    }

    protected function doAssertIsFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        $this->assertExecutableExists();
        $this->assertIsActuallyComposer();
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\PreconditionException */
    private function assertExecutableExists(): void
    {
        try {
            $this->executablePath = $this->executableFinder->find('composer');
        } catch (LogicException $e) {
            throw new PreconditionException($this, $this->t(
                'Cannot find Composer.',
                null,
                $this->d()->exceptions(),
            ), 0, $e);
        }
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\PreconditionException */
    private function assertIsActuallyComposer(): void
    {
        if (!$this->isValidExecutable()) {
            throw new PreconditionException($this, $this->t(
                'The Composer executable at %name is invalid.',
                $this->p(['%name' => $this->executablePath]),
                $this->d()->exceptions(),
            ));
        }
    }

    private function isValidExecutable(): bool
    {
        try {
            $output = $this->getComposerOutput();
            $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (ExceptionInterface|JsonException) {
            return false;
        }

        if (!isset($data['application']['name'])) {
            return false;
        }

        return $data['application']['name'] === 'Composer';
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\ExceptionInterface */
    private function getComposerOutput(): string
    {
        $this->composerProcessRunner->run(['--format=json'], null, [], $this->outputCallback);

        $output = $this->outputCallback->getOutput();
        $this->outputCallback->clearOutput();

        return implode('', $output);
    }
}
