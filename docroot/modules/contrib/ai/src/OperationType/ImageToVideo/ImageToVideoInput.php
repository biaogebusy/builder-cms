<?php

namespace Drupal\ai\OperationType\ImageToVideo;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for audio to audio input.
 */
class ImageToVideoInput extends InputBase implements InputInterface {

  /**
   * The audio file to convert.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile
   */
  private ImageFile $file;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The audio file to convert.
   */
  public function __construct(ImageFile $file) {
    $this->file = $file;
  }

  /**
   * Get the mp3 binary to convert into another binary.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The binary.
   */
  public function getImageFile(): ImageFile {
    return $this->file;
  }

  /**
   * Set the audio file.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The audio file.
   */
  public function setImageFile(ImageFile $file) {
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
      'file' => $this->file->toArray(),
    ];
  }

}
