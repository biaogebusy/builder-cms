<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\FileSyncer\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Exception\ExceptionInterface;
use PhpTuf\ComposerStager\API\Exception\IOException;
use PhpTuf\ComposerStager\API\Exception\LogicException;
use PhpTuf\ComposerStager\API\FileSyncer\Service\FileSyncerInterface;
use PhpTuf\ComposerStager\API\Filesystem\Service\FilesystemInterface;
use PhpTuf\ComposerStager\API\Finder\Service\ExecutableFinderInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathFactoryInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathListFactoryInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Process\Service\RsyncProcessRunnerInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\Internal\Translation\Factory\TranslatableAwareTrait;

/**
 * @package FileSyncer
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class FileSyncer implements FileSyncerInterface
{
    use TranslatableAwareTrait;

    public function __construct(
        private readonly EnvironmentInterface $environment,
        private readonly ExecutableFinderInterface $executableFinder,
        private readonly FilesystemInterface $filesystem,
        private readonly PathFactoryInterface $pathFactory,
        private readonly PathListFactoryInterface $pathListFactory,
        private readonly RsyncProcessRunnerInterface $rsync,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        $this->setTranslatableFactory($translatableFactory);
    }

    public function sync(
        PathInterface $source,
        PathInterface $destination,
        ?PathListInterface $exclusions = null,
        ?OutputCallbackInterface $callback = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        $this->environment->setTimeLimit($timeout);

        $exclusions ??= $this->pathListFactory->create();

        $this->assertRsyncIsAvailable();
        $this->assertSourceAndDestinationAreDifferent($source, $destination);
        $this->assertSourceIsValid($source);

        $this->runCommand($exclusions, $source, $destination, $callback, $timeout);
    }

    /** @infection-ignore-all This only makes any difference on Windows, whereas Infection is only run on Linux. */
    private function makeRelativeToSource(string $sourceAbsolute): string
    {
        $position = strpos($sourceAbsolute, '/');
        $sourceRelative = substr($sourceAbsolute, (int) $position);

        return ltrim($sourceRelative, '/');
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\LogicException */
    private function assertRsyncIsAvailable(): void
    {
        $this->executableFinder->find('rsync');
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\LogicException */
    private function assertSourceAndDestinationAreDifferent(PathInterface $source, PathInterface $destination): void
    {
        if ($source->absolute() === $destination->absolute()) {
            throw new LogicException(
                $this->t(
                    'The source and destination directories cannot be the same at %path',
                    $this->p(['%path' => $source->absolute()]),
                    $this->d()->exceptions(),
                ),
            );
        }
    }

    /** @throws \PhpTuf\ComposerStager\API\Exception\LogicException */
    private function assertSourceIsValid(PathInterface $source): void
    {
        if (!$this->filesystem->fileExists($source)) {
            throw new LogicException($this->t(
                'The source directory does not exist at %path',
                $this->p(['%path' => $source->absolute()]),
                $this->d()->exceptions(),
            ));
        }

        if (!$this->filesystem->isDir($source)) {
            throw new LogicException($this->t(
                'The source directory is not actually a directory at %path',
                $this->p(['%path' => $source->absolute()]),
                $this->d()->exceptions(),
            ));
        }
    }

    /**
     * @param int<0, max> $timeout
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     */
    private function runCommand(
        ?PathListInterface $exclusions,
        PathInterface $source,
        PathInterface $destination,
        ?OutputCallbackInterface $callback,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        $this->ensureDestinationDirectoryExists($destination);
        $command = $this->buildCommand($source, $destination, $exclusions);

        try {
            $this->rsync->run(
                $command,
                $this->pathFactory->create('/', $source),
                [],
                $callback,
                $timeout,
            );
        } catch (ExceptionInterface $e) {
            throw new IOException($e->getTranslatableMessage(), 0, $e);
        }
    }

    /**
     * Ensures that the destination directory exists. This has no effect if it already does.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     */
    private function ensureDestinationDirectoryExists(PathInterface $destination): void
    {
        $this->filesystem->mkdir($destination);
    }

    /** @return array<string> */
    private function buildCommand(
        PathInterface $source,
        PathInterface $destination,
        ?PathListInterface $exclusions,
    ): array {
        $exclusions ??= $this->pathListFactory->create();
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $exclusions = $exclusions->getAll();

        $sourceAbsolute = $source->absolute();
        $destinationAbsolute = $destination->absolute();

        // The unusual requirement to support syncing a directory with its own
        // descendant requires a unique approach, which has been documented here:
        // {@see https://serverfault.com/q/1094803/956603}.
        $command = [
            // Archive mode--the same as -rlptgoD (no -H), or --recursive,
            // --links, --perms, --times, --group, --owner, --devices, --specials.
            '--archive',
            // Skip files based on contents, not modification time and filesize.
            '--checksum',
            // Delete extraneous files from destination directories. Note: Using
            // --delete-after rather than alternatives prevents "file has
            // vanished" errors when syncing a directory with its own ancestor.
            '--delete-after',
            // Increase verbosity.
            '--verbose',
        ];

        // Prevent infinite recursion if the source is inside the destination.
        if (str_starts_with($sourceAbsolute, $destinationAbsolute)) {
            $exclusions[] = self::getRelativePath($destinationAbsolute, $sourceAbsolute);
        }

        foreach ($exclusions as $exclusion) {
            $exclusion = $this->pathFactory->create($exclusion, $source);
            // A leading slash anchors paths to the source directory root,
            // preventing incorrect partial matches.
            $relativePath = self::getRelativePath($this->pathFactory->create('/')->absolute(), $exclusion->absolute());
            $relativePath = str_replace($sourceAbsolute, '', $relativePath);

            $command[] = sprintf('--exclude=%s', $relativePath);
        }

        // A trailing slash is added to the source directory so the CONTENTS
        // of the directory are synced, not the directory itself.
        $command[] = $this->makeRelativeToSource($sourceAbsolute) . '/';

        $command[] = $this->makeRelativeToSource($destinationAbsolute);

        return $command;
    }

    private static function getRelativePath(string $ancestor, string $path): string
    {
        $ancestor .= '/';

        if (str_starts_with($path, $ancestor)) {
            return substr($path, strlen($ancestor));
        }

        return $path;
    }
}
