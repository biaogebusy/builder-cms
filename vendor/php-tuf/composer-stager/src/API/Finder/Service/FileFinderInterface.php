<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Finder\Service;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;

/**
 * Recursively finds all files "underneath" or "inside" a directory.
 *
 * @package Finder
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface FileFinderInterface
{
    /**
     * Recursively finds all files "underneath" or "inside" a given directory.
     *
     * Returns files only--no directories.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $directory
     *   The directory to search.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathListInterface|null $exclusions
     *   Paths to exclude, relative to the active directory.
     *
     * @return array<string>
     *   A sorted list of absolute file pathnames, each beginning with the
     *   given directory. For example, given "/var/www" as a directory:
     *
     *   - /var/www/four/five/six.txt
     *   - /var/www/one.txt
     *   - /var/www/two/three.txt
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     *   If the directory cannot be found or is not actually a directory.
     */
    public function find(PathInterface $directory, ?PathListInterface $exclusions = null): array;
}
