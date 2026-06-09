<?php

namespace Drupal\ai\OperationType\Moderation;

use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for moderation input.
 */
class ModerationInput extends InputBase implements InputInterface {

  /**
   * The prompts to convert to verify.
   *
   * @var string
   */
  private string $prompt;

  /**
   * The constructor.
   *
   * @param string $prompt
   *   The prompt to convert to verify.
   */
  public function __construct(string $prompt) {
    $this->prompt = $prompt;
  }

  /**
   * Get the prompt.
   *
   * @return string
   *   The prompt.
   */
  public function getPrompt(): string {
    return $this->prompt;
  }

  /**
   * Set the prompt.
   *
   * @param string $prompt
   *   The prompt.
   */
  public function setPrompt(string $prompt) {
    $this->prompt = $prompt;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->prompt;
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
      'prompt' => $this->prompt,
    ];
  }

}
