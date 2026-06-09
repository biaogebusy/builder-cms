<?php

namespace Drupal\ai\OperationType\AudioToAudio;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for audio to audio output.
 */
class AudioToAudioOutput implements OutputInterface {

  /**
   * The audio file.
   *
   * @var \Drupal\ai\OperationType\GenericType\AudioFile[]
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
   * @param \Drupal\ai\OperationType\GenericType\AudioFile[] $normalized
   *   The audio file.
   * @param mixed $rawOutput
   *   The raw output from the AI provider.
   * @param mixed $metadata
   *   The metadata from the AI provider.
   */
  public function __construct(array $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns a audio file object.
   *
   * @return \Drupal\ai\OperationType\GenericType\AudioFile[]
   *   The audio file object.
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
      'normalized' => array_map(
        fn($item) => $item->toArray(),
        $this->normalized
      ),
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }

  /**
   * Create the output from an array.
   *
   * @param array $data
   *   The data to create the output from.
   *
   * @return \Drupal\ai\OperationType\AudioToAudio\AudioToAudioOutput
   *   The output object.
   */
  public static function fromArray(array $data): AudioToAudioOutput {
    return new self(
      array_map(
        fn($item) => AudioFile::fromArray($item),
        $data['normalized']
      ),
      $data['rawOutput'],
      $data['metadata']
    );
  }

}
