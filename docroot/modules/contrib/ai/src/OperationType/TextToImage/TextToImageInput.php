<?php

namespace Drupal\ai\OperationType\TextToImage;

use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for text to image input.
 */
class TextToImageInput extends InputBase implements InputInterface {
  /**
   * The text to convert to image.
   *
   * @var string
   */
  private string $text;

  /**
   * The constructor.
   *
   * @param string $text
   *   The text to convert to image.
   */
  public function __construct(string $text) {
    $this->text = $text;
  }

  /**
   * Get the text to convert to image.
   *
   * @return string
   *   The text.
   */
  public function getText(): string {
    return $this->text;
  }

  /**
   * Set the text to convert to image.
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

  /**
   * Create an instance from an array.
   *
   * @param array $data
   *   The data to create the instance from.
   *
   * @return static
   *   The created instance.
   */
  public static function fromArray(array $data): static {
    $instance = new static($data['text'] ?? '');
    return $instance;
  }

}
