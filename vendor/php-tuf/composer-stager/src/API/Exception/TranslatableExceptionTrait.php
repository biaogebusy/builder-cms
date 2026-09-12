<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Exception;

use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;
use Throwable;

/**
 * Provides features for translatable exceptions.
 *
 * @see \PhpTuf\ComposerStager\API\Exception\ExceptionInterface
 *
 * @package Exception
 *
 * @api This trait is subject to our backward compatibility promise and may be safely depended upon.
 */
trait TranslatableExceptionTrait
{
    public function __construct(
        private readonly TranslatableInterface $translatableMessage,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct((string) $translatableMessage, $code, $previous);
    }

    /**
     * Gets the translatable form of the message with original metadata intact.
     *
     * @see \PhpTuf\ComposerStager\API\Exception\ExceptionInterface::getTranslatableMessage
     */
    public function getTranslatableMessage(): TranslatableInterface
    {
        return $this->translatableMessage;
    }
}
