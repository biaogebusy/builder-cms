<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Exception;

use PhpTuf\ComposerStager\API\Precondition\Service\PreconditionInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;
use RuntimeException as PhpRuntimeException;
use Throwable;

/**
 * This exception is thrown when an API operation has an unfulfilled precondition.
 *
 * @package Exception
 *
 * @api This class is subject to our backward compatibility promise and may be safely depended upon.
 */
class PreconditionException extends PhpRuntimeException implements ExceptionInterface
{
    use TranslatableExceptionTrait {
        TranslatableExceptionTrait::__construct as __traitConstruct;
    }

    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        private readonly PreconditionInterface $precondition,
        TranslatableInterface $translatableMessage,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $this->__traitConstruct($translatableMessage, $code, $previous);
    }

    /** Gets the unfulfilled precondition. */
    public function getPrecondition(): PreconditionInterface
    {
        return $this->precondition;
    }
}
