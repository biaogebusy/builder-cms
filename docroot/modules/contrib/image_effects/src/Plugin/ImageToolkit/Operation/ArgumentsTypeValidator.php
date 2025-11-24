<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Validates an operation's arguments for type.
 */
abstract class ArgumentsTypeValidator {

  /**
   * Validates an operation's arguments for type.
   *
   * @param array $argumentsDefinition
   *   An operation's arguments definition as returned by its ::arguments()
   *   method.
   * @param array $arguments
   *   The actual arguments passed to the operation.
   *
   * @return array
   *   The validated array of arguments.
   *
   * @throws \InvalidArgumentException
   * @throws \RuntimeException
   */
  public static function validate(array $argumentsDefinition, array $arguments): array {
    $validatedArguments = [];

    foreach ($arguments as $argument => $value) {
      if (!isset($argumentsDefinition[$argument]['type'])) {
        throw new \RuntimeException("Undefined type for argument {$argument}");
      }

      $fullType = $argumentsDefinition[$argument]['type'];
      $nullable = str_starts_with($fullType, '?');
      $type = $nullable ? substr($fullType, 1) : $fullType;
      if ($value === NULL) {
        if (!$nullable) {
          throw new \InvalidArgumentException("NULL passed to '{$argument}', expected {$fullType}");
        }
        $validatedArguments[$argument] = $value;
      }
      elseif (is_object($value)) {
        if (!is_a($value, $type)) {
          $class = get_class($value);
          throw new \InvalidArgumentException("Object of class {$class} passed to '{$argument}', expected {$fullType}");
        }
        $validatedArguments[$argument] = $value;
      }
      elseif ($type === 'array') {
        if (!is_array($value)) {
          throw new \InvalidArgumentException("Invalid value for argument '{$argument}', expected array");
        }
        $validatedArguments[$argument] = $value;
      }
      else {
        $validatedArguments[$argument] = match ($type) {
          'int' => (function (string $argument, mixed $value): int {
            if (!is_numeric($value)) {
              throw new \InvalidArgumentException("Invalid value for argument '{$argument}', expected integer");
            }
            return (int) $value;
          })($argument, $value),
          'float' => (function (string $argument, mixed $value): float {
            if (!is_numeric($value)) {
              throw new \InvalidArgumentException("Invalid value for argument '{$argument}', expected float");
            }
            return (int) $value;
          })($argument, $value),
          'bool' => (bool) $value,
          'string' => (function (string $argument, mixed $value): string {
            if (is_array($value)) {
              throw new \InvalidArgumentException("Invalid array value for argument '{$argument}', expected string");
            }
            return (string) $value;
          })($argument, $value),
          default => throw new \InvalidArgumentException("Invalid value for argument '{$argument}', expected {$fullType}"),
        };
      }
    }

    return $validatedArguments;
  }

}
