<?php

namespace Drupal\ai\OperationType\TranslateText;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object text translation output.
 */
class TranslateTextOutput implements OutputInterface {

  /**
   * The normalized translated text.
   *
   * @var string
   */
  private string $normalized;

  /**
   * The raw output from the AI provider.
   *
   * @var mixed
   */
  private mixed $rawOutput;

  /**
   * The metadata from the AI provider.
   *
   * @var mixed
   */
  private mixed $metadata;

  /**
   * The constructor.
   *
   * @param string $normalized
   *   The metadata.
   * @param mixed $rawOutput
   *   The raw output.
   * @param mixed $metadata
   *   The metadata.
   */
  public function __construct(string $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns the translated text.
   *
   * @return string
   *   The text.
   */
  public function getNormalized(): string {
    return $this->normalized;
  }

  /**
   * Gets the raw output from the AI provider.
   *
   * @return mixed
   *   The raw output.
   */
  public function getRawOutput(): mixed {
    return $this->rawOutput;
  }

  /**
   * Gets the metadata from the AI provider.
   *
   * @return mixed
   *   The metadata.
   */
  public function getMetadata(): mixed {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'normalized' => $this->normalized,
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }

}
