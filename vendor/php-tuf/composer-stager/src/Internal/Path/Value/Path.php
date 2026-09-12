<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Path\Value;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\Internal\Path\Service\PathHelperInterface;

/**
 * @package Path
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class Path implements PathInterface
{
    private string $basePathAbsolute;

    /**
     * @param string $path
     *   The path string may be absolute or relative to the current working
     *   directory as returned by `getcwd()` at runtime, e.g., "/var/www/example"
     *   or "example". Nothing needs to actually exist at the path.
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface|null $basePath
     *   Optionally override the base path used for relative paths.
     *   Nothing needs to actually exist at the path. Therefore, it is simply
     *   assumed to represent a directory, as opposed to a file--even if
     *   it has an extension, which is no guarantee of type.
     */
    public function __construct(
        private readonly PathHelperInterface $pathHelper,
        private readonly string $path,
        ?PathInterface $basePath = null,
    ) {
        // Especially since it accepts relative paths, an immutable path value
        // object should be immune to environmental details like the current
        // working directory. Cache the CWD at time of creation.
        $this->basePathAbsolute = $basePath instanceof PathInterface
            ? $basePath->absolute()
            : $this->getcwd();
    }

    public function absolute(): string
    {
        return $this->doAbsolute($this->basePathAbsolute);
    }

    public function isAbsolute(): bool
    {
        if ($this->hasProtocol($this->path)) {
            return true;
        }

        return $this->pathHelper->isAbsolute($this->path);
    }

    public function isRelative(): bool
    {
        return !$this->isAbsolute();
    }

    public function relative(PathInterface $basePath): string
    {
        $basePathAbsolute = $basePath->absolute();

        return $this->doAbsolute($basePathAbsolute);
    }

    /**
     * In order to avoid class dependencies, PHP's internal getcwd() function is
     * called directly here.
     */
    private function getcwd(): string
    {
        // It is technically possible for getcwd() to fail and return false. (For
        // example, on some Unix variants, this check will fail if any one of the
        // parent directories does not have the readable or search mode set, even
        // if the current directory does.) But the likelihood is probably so slight
        // that it hardly seems worth cluttering up client code handling theoretical
        // IO exceptions. Instead, fall back to a non-existent path in the temporary
        // directory to avoid throwing errors or operating on unintended directories.
        $getcwd = getcwd();

        if ($getcwd === false) {
            return sys_get_temp_dir() . '/composer-stager/error-' . md5(microtime());
        }

        return $getcwd;
    }

    private function doAbsolute(string $basePathAbsolute): string
    {
        if ($this->hasProtocol($this->path)) {
            return $this->getProtocol($this->path) . $this->pathHelper->canonicalize($this->stripProtocol($this->path));
        }

        if ($this->pathHelper->isAbsolute($this->pathHelper->canonicalize($this->path))) {
            return $this->pathHelper->canonicalize($this->path);
        }

        if ($this->hasProtocol($this->basePathAbsolute)) {
            return rtrim($this->basePathAbsolute, '/') . '/' . $this->pathHelper->canonicalize($this->path);
        }

        return $this->pathHelper->canonicalize($basePathAbsolute . '/' . $this->path);
    }

    private function hasProtocol(string $path): bool
    {
        return $this->getProtocol($path) !== '';
    }

    private function stripProtocol(string $path): string
    {
        return substr($path, strlen($this->getProtocol($path)));
    }

    private function getProtocol(string $path): string
    {
        preg_match('#^[a-zA-Z]+:/{2,3}#', $path, $matches);

        return $matches[0] ?? '';
    }
}
