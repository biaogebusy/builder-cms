<?php

namespace Drupal\ai\DataTypeConverter;

/**
 * Interface for applies result.
 */
interface AppliesResultInterface {

  /**
   * Return true/false if converter applicable.
   *
   * @return bool
   *   TRUE if converter applicable, FALSE otherwise.
   */
  public function applies(): bool;

  /**
   * Return true/false if converter valid.
   *
   * @return bool
   *   TRUE if converter valid, FALSE otherwise.
   */
  public function valid(): bool;

  /**
   * Return reason for result.
   *
   * @return string
   *   Reason for result.
   */
  public function getReason(): string;

  /**
   * Set reason for result.
   *
   * @param string $reason
   *   Reason for result.
   *
   * @return static
   *   The result.
   */
  public function setReason(string $reason): static;

}
