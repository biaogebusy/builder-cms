<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Process\Service;

use PhpTuf\ComposerStager\API\Process\Value\OutputTypeEnum;

/**
 * Receives streamed process output.
 *
 * This provides an interface for output callbacks accepted by API classes.
 *
 * @package Process
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface OutputCallbackInterface
{
    /** Clears current error process output. */
    public function clearErrorOutput(): void;

    /** Clears current process output. */
    public function clearOutput(): void;

    /**
     * Gets the current process error output (STDERR).
     *
     * @return array<string>
     *   Returns the cumulative error output captured--since first invoked or last
     *   cleared--as an array of one-line strings. Note that output may contain special
     *   characters depending on the process. Be careful with untrusted data.
     *
     *   Example:
     *   ```php
     *   $composer->run(['info', 'missing/package'], null, $callback);
     *   var_dump($callback->getErrorOutput();
     *   ```
     *   The above will output something like this (simplified slightly for clarity):
     *   ```
     *   Array
     *   (
     *       [0] => In ShowCommand.php line 324:
     *       [1] =>
     *       [2] => Package "missing/package" not found, try using --available (-a) to show all
     *       [3] => available packages.
     *       [4] =>
     *       [5] => show [--all] [--locked] [-i|--installed] ...
     *   )
     *   ```
     */
    public function getErrorOutput(): array;

    /**
     * Gets the current process output (STDOUT).
     *
     * @return array<string>
     *   Returns the cumulative output captured--since first invoked or last
     *   cleared--as an array of one-line strings. Note that output may contain special
     *   characters depending on the process. Be careful with untrusted data.
     *
     *   Example:
     *   ```php
     *   $composer->run(['composer', 'about'], null, $callback);
     *   var_dump($callback->getErrorOutput();
     *   ```
     *   The above will output something like this (simplified slightly for clarity):
     *   ```
     *   Array
     *   (
     *       [0] => Composer - Dependency Manager for PHP
     *       [1] => Composer is a dependency manager tracking local dependencies of your projects and libraries.
     *       [2] => See https://getcomposer.org/ for more information.
     *   )
     *   ```
     */
    public function getOutput(): array;

    /**
     * @param string $buffer
     *   An output buffer as returned by a process--may be multiple lines or contain
     *   special characters depending on the process. Be careful with untrusted data.
     */
    public function __invoke(OutputTypeEnum $type, string $buffer): void;
}
