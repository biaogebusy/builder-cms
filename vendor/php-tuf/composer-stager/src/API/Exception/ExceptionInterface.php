<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Exception;

use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;
use Throwable;

/**
 * An interface that all concrete exceptions must implement.
 *
 * @package Exception
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface ExceptionInterface extends Throwable
{
    /**
     * Gets the translatable form of the message with original metadata intact.
     *
     * @see \PhpTuf\ComposerStager\API\Exception\TranslatableExceptionTrait
     */
    public function getTranslatableMessage(): TranslatableInterface;
}
