<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Process\Factory;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Factory\ProcessFactoryInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\Internal\Process\Service\Process;

/**
 * @package Process
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class ProcessFactory implements ProcessFactoryInterface
{
    public function __construct(
        private readonly SymfonyProcessFactoryInterface $symfonyProcessFactory,
        private readonly TranslatableFactoryInterface $translatableFactory,
    ) {
    }

    public function create(array $command, ?PathInterface $cwd = null, array $env = []): ProcessInterface
    {
        return new Process($this->symfonyProcessFactory, $this->translatableFactory, $command, $cwd, $env);
    }
}
