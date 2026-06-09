<?php

namespace Drupal\ai\OperationType\Summarization;

use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for summarize input.
 */
class SummarizationInput extends InputBase implements InputInterface {

  /**
   * The text to summarize.
   *
   * @var string
   */
  private string $text;

  /**
   * Optional prompt to guide the summarization.
   *
   * Some models allow customizing how summarization is performed.
   *
   * @var string|null
   */
  private ?string $prompt;

  /**
   * The constructor.
   *
   * @param string $text
   *   The text to summarize.
   * @param string|null $prompt
   *   Optional prompt to guide the summarization.
   */
  public function __construct(string $text, ?string $prompt = NULL) {
    $this->text = $text;
    $this->prompt = $prompt;
  }

  /**
   * Get the text to summarize.
   *
   * @return string
   *   The text.
   */
  public function getText(): string {
    return $this->text;
  }

  /**
   * Get the optional prompt.
   *
   * @return string|null
   *   The prompt or null if not set.
   */
  public function getPrompt(): ?string {
    return $this->prompt;
  }

  /**
   * Set the text to summarize.
   *
   * @param string $text
   *   The text.
   */
  public function setText(string $text): void {
    $this->text = $text;
  }

  /**
   * Set the optional prompt.
   *
   * @param string|null $prompt
   *   The prompt.
   */
  public function setPrompt(?string $prompt): void {
    $this->prompt = $prompt;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->text;
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
    $data = [
      'text' => $this->text,
    ];
    if ($this->prompt !== NULL) {
      $data['prompt'] = $this->prompt;
    }
    return $data;
  }

}
