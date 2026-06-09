<?php

namespace Drupal\ai\OperationType\SpeechToSpeech;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for speech to speech input.
 */
class SpeechToSpeechInput extends InputBase implements InputInterface {

  /**
   * The audio file to convert.
   *
   * @var \Drupal\ai\OperationType\GenericType\AudioFile
   */
  private AudioFile $file;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\GenericType\AudioFile $file
   *   The audio file to convert.
   */
  public function __construct(AudioFile $file) {
    $this->file = $file;
  }

  /**
   * Get the mp3 binary to convert into another binary.
   *
   * @return \Drupal\ai\OperationType\GenericType\AudioFile
   *   The binary.
   */
  public function getAudioFile(): AudioFile {
    return $this->file;
  }

  /**
   * Set the audio file.
   *
   * @param \Drupal\ai\OperationType\GenericType\AudioFile $file
   *   The audio file.
   */
  public function setAudioFile(AudioFile $file) {
    $this->file = $file;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->file->getFilename();
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
      'audio_file' => $this->file->toArray(),
    ];
  }

}
