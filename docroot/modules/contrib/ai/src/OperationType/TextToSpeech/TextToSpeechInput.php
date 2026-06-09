<?php

namespace Drupal\ai\OperationType\TextToSpeech;

use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for text to speech input.
 */
class TextToSpeechInput extends InputBase implements InputInterface {
  /**
   * The text to convert to speech.
   *
   * @var string
   */
  private string $text;

  /**
   * The constructor.
   *
   * @param string $text
   *   The text to convert to speech.
   */
  public function __construct(string $text) {
    $this->text = $text;
  }

  /**
   * Get the text to convert to speech.
   *
   * @return string
   *   The text.
   */
  public function getText(): string {
    return $this->text;
  }

  /**
   * Set the text to convert to speech.
   *
   * @param string $text
   *   The text.
   */
  public function setText(string $text) {
    $this->text = $text;
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
    return [
      'text' => $this->text,
    ];
  }

}
