<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Filesystem\Service\FilesystemInterface;
use PhpTuf\ComposerStager\API\Finder\Service\FileFinderInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathFactoryInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathListFactoryInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\NoSymlinksPointOutsideTheCodebaseInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;
use PhpTuf\ComposerStager\Internal\Path\Service\PathHelperInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class NoSymlinksPointOutsideTheCodebase extends AbstractFileIteratingPrecondition implements
    NoSymlinksPointOutsideTheCodebaseInterface
{
    public function __construct(
        EnvironmentInterface $environment,
        FileFinderInterface $fileFinder,
        FilesystemInterface $filesystem,
        PathFactoryInterface $pathFactory,
        private readonly PathHelperInterface $pathHelper,
        PathListFactoryInterface $pathListFactory,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        parent::__construct(
            $environment,
            $fileFinder,
            $filesystem,
            $pathFactory,
            $pathListFactory,
            $translatableFactory,
        );
    }

    public function getName(): TranslatableInterface
    {
        return $this->t('No symlinks point outside the codebase');
    }

    public function getDescription(): TranslatableInterface
    {
        return $this->t('The codebase cannot contain symlinks that point outside the codebase.');
    }

    protected function getFulfilledStatusMessage(): TranslatableInterface
    {
        return $this->t('There are no symlinks that point outside the codebase.');
    }

    protected function assertIsSupportedFile(
        string $codebaseName,
        PathInterface $codebaseRoot,
        PathInterface $file,
    ): void {
        if (!$this->filesystem->isSymlink($file)) {
            return;
        }

        if ($this->linkPointsOutsidePath($file, $codebaseRoot)) {
            throw new PreconditionException(
                $this,
                $this->t(
                    'The %codebase_name directory at %codebase_root contains links that point outside the codebase, which is not supported. The first one is %file.',
                    $this->p([
                        '%codebase_name' => $codebaseName,
                        '%codebase_root' => $codebaseRoot->absolute(),
                        '%file' => $file->absolute(),
                    ]),
                    $this->d()->exceptions(),
                ),
            );
        }
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\IOException */
    private function linkPointsOutsidePath(PathInterface $link, PathInterface $path): bool
    {
        $target = $this->filesystem->readLink($link);

        return !$this->pathHelper->isDescendant($target->absolute(), $path->absolute());
    }
}
