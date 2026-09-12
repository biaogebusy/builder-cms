<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Process\Value;

/**
 * Defines process output type values.
 *
 * @package Process
 *
 * @api This enum is subject to our backward compatibility promise and may be safely depended upon.
 */
enum OutputTypeEnum
{
    /** Standard output (stdout). */
    case OUT;

    /** Standard error (stderr). */
    case ERR;
}
