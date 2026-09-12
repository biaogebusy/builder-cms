<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Environment\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;

/**
 * @package Environment
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class Environment implements EnvironmentInterface
{
    public function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public function setTimeLimit(int $seconds): bool
    {
        if (!function_exists('set_time_limit')) {
            return false;
        }

        return set_time_limit($seconds);
    }
}
