<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Finder\Service;

/**
 * Finds executables.
 *
 * @package Finder
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface ExecutableFinderInterface
{
    /**
     * Finds the path to a given executable.
     *
     * @param string $name
     *   The machine name of the executable, e.g., "composer" or "rsync".
     *
     * @return string
     *   The path to the executable.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\LogicException
     *   If the executable cannot be found.
     */
    public function find(string $name): string;
}
