<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Exception\ExceptionInterface;
use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Filesystem\Service\FilesystemInterface;
use PhpTuf\ComposerStager\API\Finder\Service\FileFinderInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathFactoryInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathListFactoryInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
abstract class AbstractFileIteratingPrecondition extends AbstractPrecondition
{
    /**
     * @param string $codebaseName
     *   The name of the codebase in question, i.e., "active" or "staging".
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $codebaseRoot
     *   The codebase root directory.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $file
     *   The file in question.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     * @throws \PhpTuf\ComposerStager\API\Exception\PreconditionException
     */
    abstract protected function assertIsSupportedFile(
        string $codebaseName,
        PathInterface $codebaseRoot,
        PathInterface $file,
    ): void;

    public function __construct(
        EnvironmentInterface $environment,
        protected readonly FileFinderInterface $fileFinder,
        protected readonly FilesystemInterface $filesystem,
        protected readonly PathFactoryInterface $pathFactory,
        protected readonly PathListFactoryInterface $pathListFactory,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        parent::__construct($environment, $translatableFactory);
    }

    protected function doAssertIsFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        try {
            $exclusions ??= $this->pathListFactory->create();
            $exclusions->add($stagingDir->absolute());

            if ($this->exitEarly($activeDir, $stagingDir, $exclusions)) {
                return;
            }

            $directories = [
                'active' => $activeDir,
                'staging' => $stagingDir,
            ];

            foreach ($directories as $directoryName => $directoryRootDir) {
                $files = $this->findFiles($directoryRootDir, $exclusions);

                foreach ($files as $file) {
                    $file = $this->pathFactory->create($file);
                    $this->assertIsSupportedFile($directoryName, $directoryRootDir, $file);
                }
            }
        } catch (ExceptionInterface $e) {
            throw new PreconditionException($this, $e->getTranslatableMessage(), 0, $e);
        }
    }

    /** Determines whether to exit the "is fulfilled" test early, before expensive scanning for links. */
    protected function exitEarly(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions,
    ): bool {
        return false;
    }

    /**
     * @return array<string>
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     */
    private function findFiles(PathInterface $path, PathListInterface $exclusions): array
    {
        // Ignore non-existent directories.
        if (!$this->filesystem->fileExists($path)) {
            return [];
        }

        return $this->fileFinder->find($path, $exclusions);
    }
}
