<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\PreconditionInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;
use PhpTuf\ComposerStager\Internal\Translation\Factory\TranslatableAwareTrait;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
abstract class AbstractPreconditionsTree implements PreconditionInterface
{
    // This isn't used directly in this class--it's for children.
    use TranslatableAwareTrait;

    /** @var array<\PhpTuf\ComposerStager\API\Precondition\Service\PreconditionInterface> */
    private readonly array $children;

    final public function getLeaves(): array
    {
        $leaves = [];

        foreach ($this->children as $child) {
            $leaves[] = $child->getLeaves();
        }

        return array_merge(...$leaves);
    }

    /** Gets a status message for when the precondition is fulfilled. */
    abstract protected function getFulfilledStatusMessage(): TranslatableInterface;

    /**
     * The order in which children are evaluated is unspecified and should not be depended upon. There is no
     * guarantee that the order they are supplied in will have, or continue to have, any determinate effect.
     *
     * @see https://github.com/php-tuf/composer-stager/issues/75
     */
    public function __construct(
        private readonly EnvironmentInterface $environment,
        TranslatableFactoryInterface $translatableFactory,
        PreconditionInterface ...$children,
    ) {
        $this->setTranslatableFactory($translatableFactory);
        $this->children = $children;
    }

    public function getStatusMessage(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): TranslatableInterface {
        try {
            $this->assertIsFulfilled($activeDir, $stagingDir, $exclusions, $timeout);
        } catch (PreconditionException $e) {
            return $e->getTranslatableMessage();
        }

        return $this->getFulfilledStatusMessage();
    }

    public function isFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): bool {
        try {
            $this->assertIsFulfilled($activeDir, $stagingDir, $exclusions, $timeout);
        } catch (PreconditionException) {
            return false;
        }

        return true;
    }

    public function assertIsFulfilled(
        PathInterface $activeDir,
        PathInterface $stagingDir,
        ?PathListInterface $exclusions = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void {
        $this->environment->setTimeLimit($timeout);

        foreach ($this->getLeaves() as $leaf) {
            $leaf->assertIsFulfilled($activeDir, $stagingDir, $exclusions, $timeout);
        }
    }
}
