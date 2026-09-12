<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\NoNestingOnWindowsInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;
use PhpTuf\ComposerStager\Internal\Path\Service\PathHelperInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class NoNestingOnWindows extends AbstractPrecondition implements NoNestingOnWindowsInterface
{
    public function __construct(
        EnvironmentInterface $environment,
        private readonly PathHelperInterface $pathHelper,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        parent::__construct($environment, $translatableFactory);
    }

    public function getName(): TranslatableInterface
    {
        return $this->t('Active and staging directories not nested on Windows.');
    }

    public function getDescription(): TranslatableInterface
    {
        return $this->t('The active and staging directories cannot be nested if on Windows.');
    }

    protected function getFulfilledStatusMessage(): TranslatableInterface
    {
        return $this->t('The active and staging directories are not nested if on Windows.');
    }

    protected function doAssertIsFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        if (!$this->environment->isWindows()) {
            return;
        }

        $activeDirAbsolute = $activeDir->absolute();
        $stagingDirAbsolute = $stagingDir->absolute();

        if ($this->pathHelper->isDescendant($activeDirAbsolute, $stagingDirAbsolute)
            || $this->pathHelper->isDescendant($stagingDirAbsolute, $activeDirAbsolute)
        ) {
            throw new PreconditionException(
                $this,
                $this->t(
                    'The active and staging directories cannot be nested at %active_dir and %staging_dir, respectively.',
                    $this->p([
                        '%active_dir' => $activeDirAbsolute,
                        '%staging_dir' => $stagingDirAbsolute,
                    ]),
                    $this->d()->exceptions(),
                ),
            );
        }
    }
}
