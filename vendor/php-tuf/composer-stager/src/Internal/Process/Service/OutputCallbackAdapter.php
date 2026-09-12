<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Process\Service;

use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Value\OutputTypeEnum;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * @package Process
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class OutputCallbackAdapter implements OutputCallbackAdapterInterface
{
    public function __construct(private readonly ?OutputCallbackInterface $callback = null)
    {
    }

    public function __invoke(string $type, string $buffer): void
    {
        if (!$this->callback instanceof OutputCallbackInterface) {
            return;
        }

        $enumType = $type === SymfonyProcess::OUT
            ? OutputTypeEnum::OUT
            : OutputTypeEnum::ERR;

        call_user_func($this->callback, $enumType, $buffer);
    }
}
