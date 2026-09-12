<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Exception;

use LogicException as PHPLogicException;

/**
 * This exception represents an error in the program logic and should lead to a fix in your code.
 *
 * @package Exception
 *
 * @api This class is subject to our backward compatibility promise and may be safely depended upon.
 */
class LogicException extends PHPLogicException implements ExceptionInterface
{
    use TranslatableExceptionTrait;
}
