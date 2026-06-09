<?php

namespace Drupal\ai\Service\FunctionCalling;

use Drupal\Core\Executable\ExecutableInterface;

/**
 * Defines how AI can execute functions.
 */
interface ExecutableFunctionCallInterface extends FunctionCallInterface, ExecutableInterface {

  /**
   * Get readable output.
   *
   * This is a normalized way that you can provider a readable output for a LLM
   * to read and in certain cases take another actions.
   *
   * @return string
   *   The readable output.
   */
  public function getReadableOutput(): string;

  /**
   * Sets the output.
   *
   * @param string $output
   *   The output to set.
   */
  public function setOutput(string $output): void;

}
