<?php

namespace Drupal\ai\OperationType\Summarization;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for summarization output.
 */
class SummarizationOutput implements OutputInterface {

  /**
   * The summarized text.
   *
   * @var string
   */
  private string $summary;

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
   * @param string $summary
   *   The summarized text.
   * @param mixed $rawOutput
   *   The raw output from the provider.
   * @param mixed $metadata
   *   The metadata from the provider.
   */
  public function __construct(string $summary, mixed $rawOutput, mixed $metadata) {
    $this->summary = $summary;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns the summarized text.
   *
   * @return string
   *   The summary.
   */
  public function getSummary(): string {
    return $this->summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getNormalized(): string {
    return $this->summary;
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
      'summary' => $this->summary,
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }

}
