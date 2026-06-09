<?php

namespace Drupal\ai\OperationType\ImageToImage;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for image to image output.
 */
class ImageToImageOutput implements OutputInterface {

  /**
   * The normalized ImageType.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageType[]
   */
  private array $normalized;

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
   * @param \Drupal\ai\OperationType\GenericType\ImageType[] $normalized
   *   The metadata.
   * @param mixed $rawOutput
   *   The raw output.
   * @param mixed $metadata
   *   The metadata.
   */
  public function __construct(array $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns an array of ImageType objects.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageType[]
   *   The ImageType objects.
   */
  public function getNormalized(): array {
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
