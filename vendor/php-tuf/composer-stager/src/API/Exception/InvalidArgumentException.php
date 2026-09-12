<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Exception;

use InvalidArgumentException as PHPInvalidArgumentException;

/**
 * This exception is thrown when an argument doesn't satisfy validation rules.
 *
 * @package Exception
 *
 * @api This class is subject to our backward compatibility promise and may be safely depended upon.
 */
class InvalidArgumentException extends PHPInvalidArgumentException implements ExceptionInterface
{
    use TranslatableExceptionTrait;
}
