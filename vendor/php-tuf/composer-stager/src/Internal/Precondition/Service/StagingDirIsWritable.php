<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Filesystem\Service\FilesystemInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\StagingDirIsWritableInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class StagingDirIsWritable extends AbstractPrecondition implements StagingDirIsWritableInterface
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
        return $this->t('Staging directory is writable');
    }

    public function getDescription(): TranslatableInterface
    {
        return $this->t('The staging directory must be writable before any operations can be performed.');
    }

    protected function getFulfilledStatusMessage(): TranslatableInterface
    {
        return $this->t('The staging directory is writable.');
    }

    protected function doAssertIsFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        if (!$this->filesystem->isWritable($stagingDir)) {
            throw new PreconditionException($this, $this->t(
                'The staging directory is not writable.',
                null,
                $this->d()->exceptions(),
            ));
        }
    }
}
