<?php

namespace Drupal\ai\OperationType\TextToImage;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for text to speech output.
 */
class TextToImageOutput implements OutputInterface {

  /**
   * The normalized ImageFile.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile[]
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
   * Returns an array of ImageFile objects.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile[]
   *   The ImageFile objects.
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
    $images = [];
    foreach ($this->normalized as $image) {
      $images[] = $image->toArray();
    }
    return [
      'normalized' => $images,
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
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
    $normalized = [];
    foreach ($data['normalized'] ?? [] as $imageData) {
      $normalized[] = ImageFile::fromArray($imageData);
    }
    return new static($normalized, $data['rawOutput'] ?? NULL, $data['metadata'] ?? NULL);
  }

}
