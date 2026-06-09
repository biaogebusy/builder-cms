<?php

namespace Drupal\ai\OperationType\TextClassification;

use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for text classification input.
 */
class TextClassificationInput extends InputBase implements InputInterface {

  /**
   * The text to classify.
   *
   * @var string
   */
  private string $text;

  /**
   * The (in certain cases optional) labels to filter the classification.
   *
   * @var string[]
   */
  private array $labels = [];

  /**
   * The constructor.
   *
   * @param string $text
   *   The text to classify.
   * @param string[] $labels
   *   The (in certain cases optional) labels to filter the classification.
   */
  public function __construct(string $text, array $labels = []) {
    $this->text = $text;
    $this->labels = $labels;
  }

  /**
   * Get the text to classify.
   *
   * @return string
   *   The text.
   */
  public function getText(): string {
    return $this->text;
  }

  /**
   * Set the text to classify.
   *
   * @param string $text
   *   The text.
   */
  public function setText(string $text): void {
    $this->text = $text;
  }

  /**
   * Get the labels to filter the classification.
   *
   * @return string[]
   *   The labels to filter the classification.
   */
  public function getLabels(): array {
    return $this->labels;
  }

  /**
   * Set the labels to filter the classification.
   *
   * @param string[] $labels
   *   The labels to filter the classification.
   */
  public function setLabels(array $labels): void {
    $this->labels = $labels;
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
      'labels' => $this->labels,
    ];
  }

}
