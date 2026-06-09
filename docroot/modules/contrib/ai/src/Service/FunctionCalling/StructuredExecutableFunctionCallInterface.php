<?php

namespace Drupal\ai\Service\FunctionCalling;

/**
 * An Interface for executable function calls that returns structured output.
 */
interface StructuredExecutableFunctionCallInterface extends ExecutableFunctionCallInterface {

  /**
   * Get structured output.
   *
   * Returns the function output as a structured array for apis or other .
   *
   * @return array
   *   The structured output as an associative array.
   */
  public function getStructuredOutput(): array;

  /**
   * Set structured output.
   *
   * Sets the structured output for the function call, in a replay.
   *
   * @param array $output
   *   The structured output to set.
   */
  public function setStructuredOutput(array $output): void;

}
