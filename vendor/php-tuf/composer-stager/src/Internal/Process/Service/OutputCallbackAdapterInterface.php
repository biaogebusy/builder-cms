<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Process\Service;

/**
 * Adapts an OutputCallback to Symfony Process's callback expectations.
 *
 * @see https://symfony.com/doc/current/components/process.html#running-processes-asynchronously
 *
 * @package Process
 *
 * @internal Don't depend directly on this interface. It may be changed or removed at any time without notice.
 */
interface OutputCallbackAdapterInterface
{
    /** @see \Symfony\Component\Process\Process::readPipes */
    public function __invoke(string $type, string $buffer): void;
}
