<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\NoLinksExistOnWindowsInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class NoLinksExistOnWindows extends AbstractFileIteratingPrecondition implements NoLinksExistOnWindowsInterface
{
    public function getName(): TranslatableInterface
    {
        return $this->t('No links exist on Windows');
    }

    public function getDescription(): TranslatableInterface
    {
        return $this->t('The codebase cannot contain links if on Windows.');
    }

    protected function getFulfilledStatusMessage(): TranslatableInterface
    {
        return $this->t('There are no links in the codebase if on Windows.');
    }

    protected function exitEarly(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions,
    ): bool {
        // This is a Windows-specific precondition. No need to run it anywhere else.
        return !$this->environment->isWindows();
    }

    /**
     * @codeCoverageIgnore This code is host-specific, so it shouldn't be counted against
     *   code coverage numbers. Nevertheless, it IS covered by tests on Windows-based CI jobs.
     * @infection-ignore-all
     */
    protected function assertIsSupportedFile(
        string $codebaseName,
        PathInterface $codebaseRoot,
        PathInterface $file,
    ): void {
        if ($this->filesystem->isLink($file)) {
            throw new PreconditionException(
                $this,
                $this->t(
                    'The %codebase_name directory at %codebase_root contains links, which is not supported on Windows. The first one is %file.',
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
}
