<?php

namespace Drupal\ai\OperationType\ObjectDetection;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for object detection.
 */
class ObjectDetectionInput extends InputBase implements InputInterface {

  /**
   * The image file to classify.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile
   */
  private ImageFile $file;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The image file.
   */
  public function __construct(ImageFile $file) {
    $this->file = $file;
  }

  /**
   * Get the image in which objects should be detected.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The binary.
   */
  public function getImageFile(): ImageFile {
    return $this->file;
  }

  /**
   * Set the image file to process.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The image file to process.
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
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'file' => $this->file->toArray(),
    ];
  }

}
