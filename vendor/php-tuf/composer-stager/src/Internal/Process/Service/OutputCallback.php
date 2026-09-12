<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Process\Service;

use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Value\OutputTypeEnum;

/**
 * @package Process
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class OutputCallback implements OutputCallbackInterface
{
    /** @var array<string> */
    private array $output = [];

    /** @var array<string> */
    private array $errorOutput = [];

    public function clearErrorOutput(): void
    {
        $this->errorOutput = [];
    }

    public function clearOutput(): void
    {
        $this->output = [];
    }

    public function getErrorOutput(): array
    {
        return $this->errorOutput;
    }

    public function getOutput(): array
    {
        return $this->output;
    }

    /** @return array<string> */
    private function normalizeBuffer(string $buffer): array
    {
        // Convert Windows line endings.
        // @infection-ignore-all This only makes any difference on Windows,
        //   whereas Infection is only run on Linux.
        $buffer = str_replace("\r\n", "\n", $buffer);

        // Trim meaningless new lines at the beginning and end of buffers.
        $buffer = preg_replace("/(^\r?\n|\r?\n$)/", '', $buffer, 1);
        assert(is_string($buffer));

        // Split multiline strings into an array.
        return explode("\n", $buffer);
    }

    public function __invoke(OutputTypeEnum $type, string $buffer): void
    {
        $lines = $this->normalizeBuffer($buffer);

        if ($type === OutputTypeEnum::OUT) {
            $this->output = array_merge($this->output, $lines);
        } else {
            $this->errorOutput = array_merge($this->errorOutput, $lines);
        }
    }
}
