<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Process\Factory;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;

/**
 * Creates process objects.
 *
 * @package Process
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface ProcessFactoryInterface
{
    /**
     * Creates a process object.
     *
     * @param array<string> $command
     *   The command to run and its arguments listed as separate entries. Example:
     *   ```php
     *   $command = [
     *       'composer',
     *       'require',
     *       'example/package:"^1 || ^2"',
     *       '--with-all-dependencies',
     *   ];
     *   ```
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface|null $cwd
     *   The current working directory (CWD) for the process. If set to null,
     *   the CWD of the current PHP process will be used.
     * @param array<string|\Stringable> $env
     *   An array of environment variables, keyed by variable name with corresponding
     *   string or stringable values. In addition to those explicitly specified,
     *   environment variables set on your system will be inherited. You can
     *   prevent this by setting to `false` variables you want to remove. Example:
     *   ```php
     *   $process->setEnv(
     *       'STRING_VAR' => 'a string',
     *       'STRINGABLE_VAR' => new StringableObject(),
     *       'REMOVE_ME' => false,
     *   );
     *   ```
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\LogicException
     *   If the process cannot be created due to host configuration.
     */
    public function create(array $command, ?PathInterface $cwd = null, array $env = []): ProcessInterface;
}
