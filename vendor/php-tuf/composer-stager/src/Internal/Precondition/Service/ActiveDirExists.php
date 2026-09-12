<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Filesystem\Service\FilesystemInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\ActiveDirExistsInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class ActiveDirExists extends AbstractPrecondition implements ActiveDirExistsInterface
{
    public function __construct(
        EnvironmentInterface $environment,
        private readonly FilesystemInterface $filesystem,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        parent::__construct($environment, $translatableFactory);
    }

    public function getName(): TranslatableInterface
    {
        return $this->t('Active directory exists');
    }

    public function getDescription(): TranslatableInterface
    {
        return $this->t('There must be an active directory present before any operations can be performed.');
    }

    protected function getFulfilledStatusMessage(): TranslatableInterface
    {
        return $this->t('The active directory exists.');
    }

    protected function doAssertIsFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        if (!$this->filesystem->fileExists($activeDir)) {
            throw new PreconditionException($this, $this->t(
                'The active directory does not exist.',
                null,
                $this->d()->exceptions(),
            ));
        }

        if (!$this->filesystem->isDir($activeDir)) {
            throw new PreconditionException($this, $this->t(
                'The active directory is not actually a directory.',
                null,
                $this->d()->exceptions(),
            ));
        }
    }
}
