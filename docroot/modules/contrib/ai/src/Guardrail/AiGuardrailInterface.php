<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;

/**
 * Interface for ai_guardrail plugins.
 */
interface AiGuardrailInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Checks if the plugin is available with the current environment.
   *
   * @return bool
   *   TRUE if the plugin is available, FALSE otherwise.
   */
  public function isAvailable(): bool;

  /**
   * Processes the input and returns a GuardrailResultInterface.
   */
  public function processInput(InputInterface $input): GuardrailResultInterface;

  /**
   * Processes the output and returns a GuardrailResultInterface.
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface;

}
