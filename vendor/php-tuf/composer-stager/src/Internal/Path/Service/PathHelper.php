<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Path\Service;

use PhpTuf\ComposerStager\Internal\Translation\Factory\TranslatableAwareTrait;
use Symfony\Component\Filesystem\Path as SymfonyPath;

/**
 * @package Path
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class PathHelper implements PathHelperInterface
{
    use TranslatableAwareTrait;

    public function canonicalize(string $path): string
    {
        $path = self::normalize($path);

        // symfony/filesystem ≥v6.4.40/v7.4.11 no longer recognizes Windows drive
        // roots (e.g., "C:/") on non-Windows systems. Use a Unix sentinel root so
        // ".." segments resolve correctly, then restore the drive prefix.
        if (preg_match('#^([A-Za-z]:)(/?+)(.*)$#s', $path, $matches) === 1) {
            // Use a Unix sentinel root ('/') so '..' segments resolve correctly,
            // then restore the drive prefix. Path::canonicalize('/') always returns
            // '/' (never ''), so the result is always at least "$drive/".
            return self::deduplicateSlashes($matches[1] . SymfonyPath::canonicalize('/' . $matches[3]));
        }

        return self::deduplicateSlashes(SymfonyPath::canonicalize($path));
    }

    public function isAbsolute(string $path): bool
    {
        $path = self::normalize($path);

        // symfony/filesystem ≥v6.4.40/v7.4.11 no longer recognizes Windows paths
        // on non-Windows systems. Handle drive paths (e.g., "C:/", "C:") explicitly.
        return preg_match('#^[A-Za-z]:(/|$)#', $path) === 1
            || SymfonyPath::isAbsolute($path);
    }

    public function isDescendant(string $descendant, string $ancestor): bool
    {
        return str_starts_with($descendant, $ancestor . '/');
    }

    public function isRelative(string $path): bool
    {
        return !$this->isAbsolute($path);
    }

    /** Converts Windows backslashes to forward slashes. */
    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /** Collapses consecutive forward slashes into one. */
    private static function deduplicateSlashes(string $path): string
    {
        $result = preg_replace('#/+#', '/', $path);
        assert(is_string($result));

        return $result;
    }
}
