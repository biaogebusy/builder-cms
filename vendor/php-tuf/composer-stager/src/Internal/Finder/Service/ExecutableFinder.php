<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Finder\Service;

use PhpTuf\ComposerStager\API\Exception\LogicException;
use PhpTuf\ComposerStager\API\Finder\Service\ExecutableFinderInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\Internal\Translation\Factory\TranslatableAwareTrait;
use Symfony\Component\Process\ExecutableFinder as SymfonyExecutableFinder;

/**
 * @package Finder
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class ExecutableFinder implements ExecutableFinderInterface
{
    use TranslatableAwareTrait;

    public function __construct(
        private readonly SymfonyExecutableFinder $symfonyExecutableFinder,
        TranslatableFactoryInterface $translatableFactory,
    ) {
        $this->setTranslatableFactory($translatableFactory);
    }

    public function find(string $name): string
    {
        // Look for executable.
        $path = $this->symfonyExecutableFinder->find($name);

        // Throw exception if not found.
        if ($path === null) {
            throw new LogicException($this->t(
                "The %name executable cannot be found. Make sure it's installed and in the \$PATH",
                $this->p(['%name' => $name]),
                $this->d()->exceptions(),
            ));
        }

        return $path;
    }
}
