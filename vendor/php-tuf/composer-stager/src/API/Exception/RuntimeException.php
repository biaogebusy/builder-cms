<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Exception;

use RuntimeException as PHPRuntimeException;

/**
 * This exception is thrown if an error occurs that can only be found at runtime.
 *
 * @package Exception
 *
 * @api This class is subject to our backward compatibility promise and may be safely depended upon.
 */
class RuntimeException extends PHPRuntimeException implements ExceptionInterface
{
    use TranslatableExceptionTrait;
}
