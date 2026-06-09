<?php

namespace Drupal\ai_test\OperationType\Echo;

use Drupal\ai\OperationType\InputBase;

/**
 * Input object for echo operations.
 */
class EchoInput extends InputBase {

  /**
   * The constructor.
   *
   * @param string $input
   *   The echo input.
   */
  public function __construct(private string $input) {}

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->input;
  }

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function __toString(): string {
    return $this->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'input' => $this->input,
    ];
  }

}
