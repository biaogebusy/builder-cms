<?php

namespace Drupal\ai\OperationType\Moderation;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for moderation output.
 */
class ModerationOutput implements OutputInterface {

  /**
   * The moderation answer. True if the content is flagged, false otherwise.
   *
   * @var \Drupal\ai\OperationType\Moderation\ModerationResponse
   */
  private ModerationResponse $normalized;

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

  public function __construct(ModerationResponse $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns the new moderation bool.
   *
   * @return \Drupal\ai\OperationType\Moderation\ModerationResponse
   *   The moderation bool. True if its flagged.
   */
  public function getNormalized(): ModerationResponse {
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
