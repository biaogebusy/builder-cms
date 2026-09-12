<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\NoHardLinksExistInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class NoHardLinksExist extends AbstractFileIteratingPrecondition implements NoHardLinksExistInterface
{
    public function getName(): TranslatableInterface
    {
        return $this->t('No hard links exist');
    }

    public function getDescription(): TranslatableInterface
    {
        return $this->t('The codebase cannot contain hard links.');
    }

    protected function getFulfilledStatusMessage(): TranslatableInterface
    {
        return $this->t('There are no hard links in the codebase.');
    }

    protected function assertIsSupportedFile(
        string $codebaseName,
        PathInterface $codebaseRoot,
        PathInterface $file,
    ): void {
        if ($this->filesystem->isHardLink($file)) {
            throw new PreconditionException(
                $this,
                $this->t(
                    'The %codebase_name directory at %codebase_root contains hard links, which is not supported. The first one is %file.',
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
