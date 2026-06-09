<?php

namespace Drupal\ai\OperationType\ImageAndAudioToVideo;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for audio to audio input.
 */
class ImageAndAudioToVideoInput extends InputBase implements InputInterface {

  /**
   * The image file to convert.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile
   */
  private ImageFile $file;

  /**
   * The audio file to use.
   *
   * @var \Drupal\ai\OperationType\GenericType\AudioFile
   */
  private AudioFile $audioFile;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The audio file to convert.
   * @param \Drupal\ai\OperationType\GenericType\AudioFile $audioFile
   *   The audio file to use.
   */
  public function __construct(ImageFile $file, AudioFile $audioFile) {
    $this->file = $file;
    $this->audioFile = $audioFile;
  }

  /**
   * Get the image binary to convert into another binary.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The image file.
   */
  public function getImageFile(): ImageFile {
    return $this->file;
  }

  /**
   * Set the image file.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The image file.
   */
  public function setImageFile(ImageFile $file) {
    $this->file = $file;
  }

  /**
   * Get the audio binary to convert into another binary.
   *
   * @return \Drupal\ai\OperationType\GenericType\AudioFile
   *   The audio file.
   */
  public function getAudioFile(): AudioFile {
    return $this->audioFile;
  }

  /**
   * Set the audio file.
   *
   * @param \Drupal\ai\OperationType\GenericType\AudioFile $file
   *   The audio file.
   */
  public function setAudioFile(AudioFile $file) {
    $this->audioFile = $file;
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
      'image_file' => $this->file->toArray(),
      'audio_file' => $this->audioFile->toArray(),
    ];
  }

}
