<?php

namespace Drupal\ai\OperationType\SpeechToText;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for speech to text input.
 */
class SpeechToTextInput extends InputBase implements InputInterface {
  /**
   * The audio file to convert to text.
   *
   * @var \Drupal\ai\OperationType\GenericType\AudioFile
   */
  private AudioFile $file;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\GenericType\AudioFile $file
   *   The file to convert to text.
   */
  public function __construct(AudioFile $file) {
    $this->file = $file;
  }

  /**
   * Get the file to convert into text.
   *
   * @return \Drupal\ai\OperationType\GenericType\AudioFile
   *   The text.
   */
  public function getFile(): AudioFile {
    return $this->file;
  }

  /**
   * Get the file as binary.
   *
   * @return string
   *   The binary.
   */
  public function getBinary(): string {
    return $this->file->getBinary();
  }

  /**
   * Set the file to convert into text.
   *
   * @param \Drupal\ai\OperationType\GenericType\AudioFile $file
   *   The file.
   */
  public function setFile(AudioFile $file) {
    $this->file = $file;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return 'binary';
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
      'file' => $this->file->toArray(),
    ];
  }

}
